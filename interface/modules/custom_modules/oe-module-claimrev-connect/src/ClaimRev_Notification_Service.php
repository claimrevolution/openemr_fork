<?php

/**
 * Background service that polls ClaimRev for account notifications
 * and creates pnotes in OpenEMR so users see them in their Messages inbox.
 *
 * Runs every 60 minutes. Tracks which notifications have already been
 * delivered via mod_claimrev_notifications to prevent duplicates.
 * Marks notifications as read on ClaimRev after delivery.
 *
 * @package OpenEMR
 * @link    http://www.claimrev.com
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\ClaimRevConnector\ClaimRevApi;
use OpenEMR\Modules\ClaimRevConnector\ClaimRevException;
use OpenEMR\Modules\ClaimRevConnector\GlobalConfig;

require_once($GLOBALS['fileroot'] . "/library/pnotes.inc.php");

/**
 * Convert HTML to readable plain text, preserving paragraph breaks,
 * list structure, and table rows.
 */
function htmlToPlainText(string $html): string
{
    // Remove head/style/script blocks entirely
    $text = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
    $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', (string) $text);
    $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string) $text);

    // Block-level breaks: </p>, </div>, </tr>, </h1-6>, <br>
    $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $text);
    $text = preg_replace('/<\/(?:p|div|tr|h[1-6])>/i', "\n\n", (string) $text);

    // List items
    $text = preg_replace('/<li\b[^>]*>/i', "\n- ", (string) $text);

    // Table cells: add spacing between <td> content
    $text = preg_replace('/<\/td>\s*<td/i', "</td>  <td", (string) $text);

    // Strip remaining tags
    $text = strip_tags((string) $text);

    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Collapse runs of whitespace on each line (preserve newlines)
    $text = preg_replace('/[^\S\n]+/', ' ', $text);

    // Collapse 3+ consecutive newlines into 2
    $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);

    return trim((string) $text);
}

function start_claimrev_notifications(): void
{
    $enabled = $GLOBALS[GlobalConfig::CONFIG_ENABLE_NOTIFICATIONS] ?? '1';
    if (!$enabled) {
        return;
    }

    try {
        $api = ClaimRevApi::makeFromGlobals();
    } catch (ClaimRevException) {
        return;
    }

    try {
        $notifications = $api->getPortalNotifications(false);
    } catch (ClaimRevException) {
        return;
    }

    if (!is_array($notifications)) {
        return;
    }

    $recipientSetting = OEGlobalsBag::getInstance()->getString(GlobalConfig::CONFIG_NOTIFICATION_RECIPIENT, 'admin');
    if ($recipientSetting === '') {
        $recipientSetting = 'admin';
    }
    $recipients = array_filter(array_map(trim(...), explode(';', $recipientSetting)));
    if ($recipients === []) {
        $recipients = ['admin'];
    }

    foreach ($notifications as $notification) {
        $portalId = $notification['portalNotificationId'] ?? null;
        if ($portalId === null) {
            continue;
        }

        // Check if we already processed this notification
        $existing = QueryUtils::querySingleRow(
            "SELECT id FROM mod_claimrev_notifications WHERE portal_notification_id = ?",
            [$portalId]
        );
        if ($existing !== [] && $existing !== false) {
            continue;
        }

        $title = htmlToPlainText($notification['messageTitle'] ?? 'ClaimRev Notification');

        $bodyText = $notification['messageBodyText'] ?? '';
        if ($bodyText === '' || $bodyText === null) {
            $bodyText = $notification['messageBody'] ?? '';
        }
        $body = htmlToPlainText($bodyText);

        $messageText = "ClaimRev: " . $title . "\n\n" . $body;

        // Create pnote for each configured recipient
        $firstPnoteId = 0;
        foreach ($recipients as $recipient) {
            $pnoteId = addPnote(
                0,
                $messageText,
                0,
                1,
                "ClaimRev",
                $recipient,
                "",
                "New",
                "claimrev-notifications"
            );
            if ($firstPnoteId == 0) {
                $firstPnoteId = $pnoteId;
            }
        }

        // Track it so we don't create duplicates
        sqlInsert(
            "INSERT INTO mod_claimrev_notifications (portal_notification_id, message_title, message_body, pnote_id, created_date, processed_date) VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $portalId,
                $title,
                $body,
                $firstPnoteId,
                $notification['createdDate'] ?? date('Y-m-d H:i:s')
            ]
        );

        // Mark as read on ClaimRev so it doesn't come back next poll
        try {
            $api->setNotificationReadStatus($portalId, true);
        } catch (ClaimRevException) {
            // Non-fatal - notification was already delivered
        }
    }
}
