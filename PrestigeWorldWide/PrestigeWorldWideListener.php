<?php

namespace Statamic\Addons\PrestigeWorldWide;

use Carbon\Carbon;
// use Eluceo\iCal\Component\Alarm;
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
use Statamic\Events\StacheUpdated;

class PrestigeWorldWideListener extends Listener
{

    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        \Statamic\Events\Data\FindingFieldset::class => 'addEventTab',
        // \Statamic\Events\Data\PublishFieldsetFound::class => 'addEventTab',
        StacheUpdated::class => 'saveEventCache',
        'Form.submission.creating' => 'handleSubmission',
        'response.created' => 'handleResponse'
    ];

    /**
     * Add the events tab to the chosen entry
     *
     * @var array
     */
    public function addEventTab(FindingFieldset $eventCollection)
    {
        // Get the saved events collection from the settings
        $this->eventsCollection = $this->getConfig('my_collections_field');

        // Check if the entry is in the correct collection and if this is a page
        if ($eventCollection->type == 'entry') {
            if ($eventCollection->data->collectionName() == $this->eventsCollection) {
                $fieldset = $eventCollection->fieldset;
                $sections = $fieldset->sections();
                $fields = YAML::parse(File::get($this->getDirectory().'/resources/fieldsets/content.yaml'))['fields'];

                $sections['event'] = [
                     'display' => 'Event info',
                     'fields' => $fields
                 ];

                $contents = $fieldset->contents();
                $contents['sections'] = $sections;
                $fieldset->contents($contents);
            }
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
            if ($entry->has('pw_start_date') && $entry->published()) {
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
        $description = $entry->get('pw_description');
        $id = $entry->get('id');
        $location = $entry->get('pw_location');
        $status = $entry->get('pw_status');
        $tags = $entry->get('tags');
        $title = $entry->get('title');
        $url = $entry->url();

        if ($entry->has('pw_timezone')) {
            $timezone = $entry->get('pw_timezone');
            $use_timezone = true;
        } else {
            $timezone = null;
            $use_timezone = false;
        }

        $vEvent = new Event();

        if ($entry->get('pw_recurring') == true && $entry->get('pw_recurring_frequency') != 'CUSTOM') {
            $vEvent
                ->addRecurrenceRule($this->addRecurrenceRule($entry))
                ->setCategories($tags)
                ->setDescription($description)
                ->setDtStart($this->getCarbon($start_date))
                ->setDtEnd($this->getCarbon($end_date))
                ->setLocation($location)
                ->setStatus($status)
                ->setSummary($title)
                ->setTimezoneString($timezone)
                ->setUniqueId($id)
                ->setUrl($url)
                ->setUseTimezone($use_timezone);
        } else {
            $vEvent
                ->setCategories($tags)
                ->setDescription($description)
                ->setDtStart($this->getCarbon($start_date))
                ->setDtEnd($this->getCarbon($end_date))
                ->setLocation($location)
                ->setStatus($status)
                ->setSummary($title)
                ->setTimezoneString($timezone)
                ->setUniqueId($id)
                ->setUrl($url)
                ->setUseTimezone($use_timezone);
        }
        return $vEvent;
    }

    /**
     * Build a recurrence rule
     *
     * @return string
     */
    private function addRecurrenceRule($entry)
    {
        if ($entry->has('pw_recurring_byday')) {
            $byday = \implode(',', $entry->get('pw_recurring_byday'));
        } else {
            $byday = '';
        }
        $freq = $entry->get('pw_recurring_frequency');
        $interval = $entry->get('pw_recurring_interval');

        $vRecurr = new RecurrenceRule();
        if ($entry->get('pw_recurring_ends') == 'on') {
            $vRecurr
                ->setByDay($byday)
                ->setFreq($freq)
                ->setInterval($interval)
                ->setUntil($this->getCarbon($entry->get('pw_recurring_until')));
        } else {
            $vRecurr
                ->setByDay($byday)
                ->setFreq($freq)
                ->setInterval($interval)
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
            return Carbon::createFromTimestamp($datetime);
        } else {
            return Carbon::parse($datetime);
        }
    }
}
