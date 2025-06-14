<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="widgetLoader">
    <ActivityIndicator
      :loading-message="finalLoadingMessage"
      :loading="loading"
    />
    <div v-show="loadingFailed">
      <h2 v-if="widgetName">{{ widgetName }}</h2>
      <div v-if="!loadingFailedRateLimit" class="notification system notification-error">
        {{ translate('General_ErrorRequest', '', '') }}
        <a
          rel="noreferrer noopener"
          target="_blank"
          :href="externalRawLink('https://matomo.org/faq/troubleshooting/faq_19489/')"
          v-if="hasErrorFaqLink"
        >
          {{ translate('General_ErrorRequestFaqLink') }}
        </a>
      </div>
      <div v-else class="notification system notification-error">
        {{ translate('General_ErrorRateLimit') }}
      </div>
    </div>
    <div class="theWidgetContent" ref="widgetContent" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import ActivityIndicator from '../ActivityIndicator/ActivityIndicator.vue';
import { translate } from '../translate';
import Matomo from '../Matomo/Matomo';
import AjaxHelper from '../AjaxHelper/AjaxHelper';
import { NotificationsStore } from '../Notification';
import MatomoUrl from '../MatomoUrl/MatomoUrl';
import ComparisonsStoreInstance from '../Comparisons/Comparisons.store.instance';
import SearchFiltersPersistenceStoreInstance from '../SearchFiltersPersistence/SearchFiltersPersistence.store';

interface WidgetLoaderState {
  loading: boolean;
  loadingFailed: boolean;
  loadingFailedRateLimit: boolean;
  changeCounter: number;
  lastWidgetAbortController: null|AbortController;
}

/**
 * Loads any custom widget or URL based on the given parameters.
 *
 * The currently active idSite, period, date and segment (if needed) is automatically
 * appended to the parameters. If this widget is removed from the DOM and requests are in
 * progress, these requests will be aborted. A loading message or an error message on failure
 * is shown as well. It's kinda similar to ng-include but there it is not possible to
 * listen to HTTP errors etc.
 *
 * Example:
 * <WidgetLoader :widget-params="{module: '', action: '', ...}"/>
 */
export default defineComponent({
  props: {
    widgetParams: Object,
    widgetName: String,
    loadingMessage: String,
  },
  components: {
    ActivityIndicator,
  },
  data(): WidgetLoaderState {
    return {
      loading: false,
      loadingFailed: false,
      loadingFailedRateLimit: false,
      changeCounter: 0,
      lastWidgetAbortController: null,
    };
  },
  watch: {
    widgetParams(parameters: QueryParameters) {
      if (parameters) {
        this.loadWidgetUrl(parameters, this.changeCounter += 1);
      }
    },
  },
  computed: {
    finalLoadingMessage() {
      if (this.loadingMessage) {
        return this.loadingMessage;
      }

      if (!this.widgetName) {
        return translate('General_LoadingData');
      }

      return translate('General_LoadingPopover', this.widgetName);
    },
    hasErrorFaqLink() {
      const isGeneralSettingsAdminEnabled = Matomo.config.enable_general_settings_admin;
      const isPluginsAdminEnabled = Matomo.config.enable_plugins_admin;

      return Matomo.hasSuperUserAccess
        && (isGeneralSettingsAdminEnabled
          || isPluginsAdminEnabled);
    },
  },
  mounted() {
    if (this.widgetParams) {
      this.loadWidgetUrl(this.widgetParams as QueryParameters, this.changeCounter += 1);
    }
  },
  beforeUnmount() {
    this.cleanupLastWidgetContent();
  },
  methods: {
    abortHttpRequestIfNeeded() {
      if (this.lastWidgetAbortController) {
        this.lastWidgetAbortController.abort();
        this.lastWidgetAbortController = null;
      }
    },
    cleanupLastWidgetContent() {
      const widgetContent = this.$refs.widgetContent as HTMLElement;
      Matomo.helper.destroyVueComponent(widgetContent);
      if (widgetContent) {
        widgetContent.innerHTML = '';
      }
    },
    getWidgetUrl(parameters?: QueryParameters): QueryParameters {
      const urlParams = MatomoUrl.parsed.value;

      let fullParameters: QueryParameters = { ...(parameters || {}) };

      const paramsToForward = Object.keys({
        ...MatomoUrl.hashParsed.value,
        idSite: '',
        period: '',
        date: '',
        segment: '',
        widget: '',
      });

      paramsToForward.forEach((key) => {
        if (key === 'category' || key === 'subcategory') {
          return;
        }

        if (!(key in fullParameters)) {
          fullParameters[key] = urlParams[key];
        }
      });

      if (ComparisonsStoreInstance.isComparisonEnabled()) {
        fullParameters = {
          ...fullParameters,
          comparePeriods: urlParams.comparePeriods,
          compareDates: urlParams.compareDates,
          compareSegments: urlParams.compareSegments,
        };
      }

      if (!parameters || !('showtitle' in parameters)) {
        fullParameters.showtitle = '1';
      }

      if (Matomo.shouldPropagateTokenAuth
        && urlParams.token_auth
      ) {
        if (!Matomo.broadcast.isWidgetizeRequestWithoutSession()) {
          fullParameters.force_api_session = '1';
        }
        fullParameters.token_auth = urlParams.token_auth;
      }

      fullParameters.random = Math.floor(Math.random() * 10000);

      return fullParameters;
    },
    loadWidgetUrl(parameters: QueryParameters, thisChangeId: number) {
      this.loading = true;

      this.abortHttpRequestIfNeeded();
      this.cleanupLastWidgetContent();

      this.lastWidgetAbortController = new AbortController();

      let searchFilters = {};
      if (parameters.uniqueId) {
        searchFilters = SearchFiltersPersistenceStoreInstance.getSearchFilters(parameters.uniqueId);
      }

      AjaxHelper.fetch(this.getWidgetUrl(Object.assign(parameters, searchFilters)), {
        format: 'html',
        abortController: this.lastWidgetAbortController,
      }).then((response) => {
        if (thisChangeId !== this.changeCounter || typeof response !== 'string') {
          // another widget was requested meanwhile, ignore this response
          return;
        }

        this.lastWidgetAbortController = null;
        this.loading = false;
        this.loadingFailed = false;

        const widgetContent = this.$refs.widgetContent as HTMLElement;
        window.$(widgetContent).html(response);
        const $content = window.$(widgetContent).children();

        if (this.widgetName) {
          // we need to respect the widget title, which overwrites a possibly set report title
          let $title = $content.find('> .card-content .card-title');
          if (!$title.length) {
            $title = $content.find('> h2');
          }

          if ($title.length) {
            // required to use htmlEntities since it also escapes '{{' format items
            $title.html(Matomo.helper.htmlEntities(this.widgetName));
          }
        }

        Matomo.helper.compileVueEntryComponents($content);

        NotificationsStore.parseNotificationDivs();

        setTimeout(() => {
          Matomo.postEvent('widget:loaded', {
            parameters,
            element: $content,
          });
        });
      }).catch((response) => {
        if (thisChangeId !== this.changeCounter) {
          // another widget was requested meanwhile, ignore this response
          return;
        }

        this.lastWidgetAbortController = null;
        this.cleanupLastWidgetContent();

        this.loading = false;

        if (response.xhrStatus === 'abort') {
          return;
        }

        if (response.status === 429) {
          this.loadingFailedRateLimit = true;
        }

        this.loadingFailed = true;
      });
    },
  },
});
</script>
