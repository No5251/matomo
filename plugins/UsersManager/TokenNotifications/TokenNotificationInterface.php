<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UsersManager\TokenNotifications;

interface TokenNotificationInterface
{
    public function getTokenId(): string;

    public function getTokenName(): string;

    public function getTokenCreationDate(): string;

    public function dispatch(): bool;
}
