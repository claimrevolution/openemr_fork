<?php

/**
 * Calendar event listener that adds eligibility status indicators to
 * appointment blocks on the main OpenEMR calendar.
 *
 * When enabled via the CONFIG_ENABLE_CALENDAR_INDICATORS global setting,
 * this class listens for CalendarUserGetEventsFilter events and enriches
 * each appointment with a CSS class (eventViewClass) based on the patient's
 * primary insurance eligibility status.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Events\Appointments\CalendarUserGetEventsFilter;
use OpenEMR\Events\Core\StyleFilterEvent;

class CalendarEligibilityIndicator
{
    public function __construct(private readonly int $staleAgeDays)
    {
    }

    public function filterCalendarEvents(CalendarUserGetEventsFilter $event): CalendarUserGetEventsFilter
    {
        $eventsByDay = $event->getEventsByDays();

        // Collect all unique PIDs from the calendar events
        $pids = [];
        foreach (array_keys($eventsByDay) as $key) {
            foreach ($eventsByDay[$key] as $calEvent) {
                if (!empty($calEvent['pid'])) {
                    $pids[(int) $calEvent['pid']] = true;
                }
            }
        }

        if (empty($pids)) {
            return $event;
        }

        // Batch-load eligibility status for all PIDs in one query
        $eligMap = $this->loadEligibilityMap(array_keys($pids));

        // Apply eventViewClass to each calendar event
        foreach (array_keys($eventsByDay) as $key) {
            $eventCount = count($eventsByDay[$key]);
            for ($i = 0; $i < $eventCount; $i++) {
                $pid = $eventsByDay[$key][$i]['pid'] ?? null;
                if (empty($pid)) {
                    continue;
                }

                $eligClass = $this->determineEligClass($eligMap[(int) $pid] ?? null);
                if ($eligClass === '') {
                    continue;
                }

                $existingClass = $eventsByDay[$key][$i]['eventViewClass'] ?? '';
                $eventsByDay[$key][$i]['eventViewClass'] = trim($existingClass . ' ' . $eligClass);
            }
        }

        $event->setEventsByDays($eventsByDay);
        return $event;
    }

    public function addCalendarStylesheet(StyleFilterEvent $event): void
    {
        if ($event->getPageName() === 'pnuserapi.php' || $event->getPageName() === 'pnadmin.php') {
            $styles = $event->getStyles();
            $styles[] = $this->getAssetPath() . 'css/calendar-eligibility.css';
            $event->setStyles($styles);
        }
    }

    /**
     * Load eligibility data for a batch of patient IDs.
     *
     * @param int[] $pids
     * @return array<int, array{status: ?string, individual_json: ?string, last_date: ?string}>
     */
    private function loadEligibilityMap(array $pids): array
    {
        if (empty($pids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $sql = "SELECT pid, status, individual_json,
                       COALESCE(last_checked, create_date) as last_date
                FROM mod_claimrev_eligibility
                WHERE pid IN ({$placeholders})
                AND payer_responsibility = 'P'";

        $result = sqlStatement($sql, $pids);

        $map = [];
        while ($row = sqlFetchArray($result)) {
            $map[(int) $row['pid']] = $row;
        }

        return $map;
    }

    /**
     * Determine the CSS class for a patient's eligibility record.
     */
    private function determineEligClass(?array $eligRecord): string
    {
        if ($eligRecord === null) {
            return 'event_elig_unchecked';
        }

        $status = strtolower($eligRecord['status'] ?? '');

        // Pending states
        if (in_array($status, ['waiting', 'creating'], true)) {
            return 'event_elig_pending';
        }

        // Error states
        if (in_array($status, ['error', 'senderror'], true)) {
            return 'event_elig_error';
        }

        // Check for staleness
        if (!empty($eligRecord['last_date'])) {
            $daysSinceCheck = (int) ((time() - strtotime((string) $eligRecord['last_date'])) / 86400);
            if ($daysSinceCheck >= $this->staleAgeDays) {
                return 'event_elig_stale';
            }
        }

        // Success — check coverage status
        if ($status === 'success') {
            $individualJson = $eligRecord['individual_json'] ?? null;
            if ($individualJson !== null) {
                $summaries = AppointmentsPage::getEligibilitySummary($individualJson);
                if ($summaries !== null && count($summaries) > 0) {
                    if ($summaries[0]->status === 'Active Coverage') {
                        return 'event_elig_active';
                    }
                    return 'event_elig_inactive';
                }
            }
            // Success but no parseable data
            return 'event_elig_active';
        }

        return '';
    }

    private function getAssetPath(): string
    {
        return '/interface/modules/custom_modules/oe-module-claimrev-connect/public/assets/';
    }
}
