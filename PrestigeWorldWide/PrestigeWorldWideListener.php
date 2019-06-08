<?php

namespace Statamic\Addons\PrestigeWorldWide;

use Carbon\Carbon;
use Eluceo\iCal\Component\Alarm;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;
use Eluceo\iCal\Property\Event\RecurrenceRule;
use Statamic\API\Config;
use Statamic\API\Str;
use Statamic\API\File;
use Statamic\API\YAML;
use Statamic\API\Nav;
use Statamic\API\Entry;
use Statamic\Data\Data;
use Statamic\API\Collection;
use Statamic\Extend\Listener;
use Statamic\Events\Data\FindingFieldset;
use Statamic\Contracts\Forms\Submission;
use Illuminate\Http\Response;

class PrestigeWorldWideListener extends Listener
{

    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        \Statamic\Events\Data\FindingFieldset::class => 'addEventTab',
        \Statamic\Events\Data\PublishFieldsetFound::class => 'addEventTab',
        \Statamic\Events\Data\EntrySaved::class => 'saveEventCache',
        'Form.submission.creating' => 'handleSubmission',
        'response.created' => 'handleResponse'
    ];

    /**
     * Add the events tab to the chosen entry
     *
     * @var array
     */
    public function addEventTab($event)
    {

        // Get the current URL
        $this->url = $_SERVER['REQUEST_URI'];

        // Get the saved events collection from the settings
        $this->eventsCollection = $this->getConfig('my_collections_field');

        // Check if the entry is in the correct collection
        if (($event->type == 'entry') && (strpos($this->url, $this->eventsCollection) == true)) {
            $fieldset = $event->fieldset;
            $sections = $fieldset->sections();
            $fields = YAML::parse(File::get($this->getDirectory() . '/resources/fieldsets/content.yaml'))['fields'];

            if ($this->getConfig('event_timezone') == false) {
                // Remove the custom timezone based on the addon setting
                unset($fields['pw_timezone']);
            }

            $sections['event'] = [
                'display' => 'Event info',
                'fields' => $fields
            ];

            $contents = $fieldset->contents();
            $contents['sections'] = $sections;

            $fieldset->contents($contents);
        }
    }

    /**
     * Get the entry id from the session and add to the form submission
     *
     * @var array
     */
    public function handleSubmission(Submission $submission)
    {
        $entry_id = session()->pull('pw_id', 'default');
        $submission->set('pw_id', $entry_id);

        return [
            'submission' => $submission
        ];
    }

    /**
     * Add the entry id to the session
     *
     * @var array
     */
    public function handleResponse(Response $response)
    {
        $view       = $response->getOriginalContent();
        $entry_id   = $view->getData()['id'];

        if ($view->getData()['id'] !== null) {
            session(['pw_id' => $entry_id]);
        }
    }

    /**
     * Render all events as an ical file and put it in cache
     *
     * @var array
     */
    public function saveEventCache($event)
    {
        $entries = Entry::all();
        $vCalendar = new Calendar(Config::getSiteUrl());

        foreach ($entries as $entry) {
            if ($entry->has('pw_start_date')) {
                $vEvent = $this->addEventData($entry, $entry->get('pw_start_date'), $entry->get('pw_end_date'));
                $vCalendar->addComponent($vEvent);

                if ($entry->has('pw_recurring_manual')) {
                    foreach ($entry->get('pw_recurring_manual') as $custom_event) {
                        $vEvent = $this->addEventData($entry, $custom_event['pw_recurring_manual_start'], $custom_event['pw_recurring_manual_end']);
                        $vCalendar->addComponent($vEvent);
                    }
                }
            }
        }

        $this->cache->put('pw_ical', $vCalendar->render());
    }

    /**
     * Add event data
     *
     * @return array
     */
    private function addEventData($entry, $start_date, $end_date)
    {
        $vEvent = new Event();
        $entry_id = $entry->get('id');
        $entry_title = $entry->get('title');
        $entry_description = $entry->get('pw_description');
        $entry_location = $entry->get('pw_location');

        if ($entry->get('pw_recurring') == true && $entry->get('pw_recurring_frequency') != 'CUSTOM') {
            $vEvent
                ->setDtStart($this->getCarbon($start_date))
                ->setDtEnd($this->getCarbon($end_date))
                ->setLocation($entry_location)
                ->setSummary($entry_title)
                ->setUniqueId($entry_id)
                ->setStatus($this->getEventStatus($entry))
                ->addRecurrenceRule($this->addRecurrenceRule($entry))
                ->setDescription($entry_description);
        } else {
            $vEvent
                ->setDtStart($this->getCarbon($start_date))
                ->setDtEnd($this->getCarbon($end_date))
                ->setLocation($entry_location)
                ->setSummary($entry_title)
                ->setUniqueId($entry_id)
                ->setStatus($this->getEventStatus($entry))
                ->setDescription($entry_description);
        }
        return $vEvent;
    }

    /**
     * Check for an event status
     *
     * @return string
     */
    private function getEventStatus($entry)
    {
        if ($entry->get('pw_status') != null) {
            return $entry->get('pw_status');
        } else {
            return 'Confirmed';
        }
    }

    /**
     * Build a recurrence rule
     *
     * @return string
     */
    private function addRecurrenceRule($entry)
    {
        $vRecurr = new RecurrenceRule();
        if ($entry->get('pw_recurring_ends') == 'on') {
            $vRecurr
                ->setFreq($entry->get('pw_recurring_frequency'))
                ->setInterval($entry->get('pw_recurring_interval'))
                ->setByDay($entry->get('pw_recurring_byday'))
                ->setUntil($this->getCarbon($entry->get('pw_recurring_until')));
        } else {
            $vRecurr
                ->setFreq($entry->get('pw_recurring_frequency'))
                ->setInterval($entry->get('pw_recurring_interval'))
                ->setByDay($entry->get('pw_recurring_byday'))
                ->setCount($entry->get('pw_recurring_count'));
        }
        return $vRecurr;
    }

    /**
     * Get the Carbon version of the datetime
     *
     * @param string|int $datetime foo
     *
     * @return Carbon\Carbon
     */
    private function getCarbon($datetime)
    {
        if (is_numeric($datetime)) {
            return Carbon::createFromTimestamp($datetime)->setTimezone('UTC');
        } else {
            return Carbon::parse($datetime)->setTimezone('UTC')->setTimezone('UTC');
        }
    }
}
