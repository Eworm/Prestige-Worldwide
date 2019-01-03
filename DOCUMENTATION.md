# About Prestige Worldwide
The idea of Prestige Worldwide isn't to be a full fledged event system within Statamic, but to add functionality to Statamic so you can use a collection for events.

## Setup
Prestige Worldwide requires __1 thing__: a Statamic collection. So create a collection before or after installing this addon. Then go to the addons page in the control panel, click on Prestige Worldwide and select the collection you want to use for your events. Each entry in this collection will be an event.

After selecting a collection you'll see an extra tab called 'Event info' on the entry page of this collection. This tab allows you to add relevant info about this event. There's info about dates, costs, location, an external url and an organizer.


You can also select which form to use for signups. And if you add a maximum number of participants PW will check if the max number of participants is reached. PW doesn't add things like titles or images, those are up to you.

* [Showing a list of events](#list)
* [Showing info on a detail page](#detail)
* [Using a signup form](#form)

## Showing a list of events <a id="list"></a>
Use these for a list of events. PW adds custom filters to a Statamic collection, the rest is pure Statamic. [More info about collections is here](https://docs.statamic.com/tags/collection).

## Get all future events <a id="future"></a>
See what we've done there?

    {{ collection:events filter="prestige_world_wide" remove="past" paginate="true" as="posts" limit="10" }}
        {{ posts scope="pw_start_date" }}
        {{ partial:block }}
        {{ /posts }}
        {{ partial:pagination }}
    {{ /collection:events }}

## Get all past events <a id="past"></a>
    {{ collection:events filter="prestige_world_wide" remove="future" paginate="true" as="posts" limit="10" }}
        {{ posts scope="pw_start_date" }}
        {{ partial:block }}
        {{ /posts }}
        {{ partial:pagination }}
    {{ /collection:events }}

## Detail page info & tags <a id="detail"></a>
The idea of PW is to give you the freedom to build your eventpage the way you want to. You can use the following variables and tags:

### Variables
* Start date: `{{ pw_start_date }}`
* End date: `{{ pw_end_date }}`
* Cost: `{{ pw_costs }}`
* Location: `{{ pw_location }}`
* URL: `{{ pw_url }}`
* Organizer: `{{ pw_organizer }}`
* Organizer email: `{{ pw_organizer_email }}`
* Max participants: `{{ pw_max_participants }}`
* Has a form: `{{ pw_has_form }}`

### Tags
* [Recurring dates](#recurring)
* [Number of signups](#participants)
* [Full or not?](#full)
* [ICS export](#ics)
* [Add to Google Calendar](#gcal)
* [Form or not?](#form)

***

## Recurring dates <a id="recurring"></a>
<table>
    <tbody>
        <tr>
            <td>Get</td>
            <td>`{{ prestige_world_wide:recurring }}{{ /prestige_world_wide:recurring }}`</td>
            <td>Returns an array</td>
        </tr>
    </tbody>
</table>

**Example**   

    <ul>
        {{ prestige_world_wide:recurring }}
        <li>{{ start format="j F Y, G:i" }} - {{ end format="j F Y, G:i" }}</li>
        {{ /prestige_world_wide:recurring }}
    </ul>

## Number of signups <a id="participants"></a>
<table>
    <tbody>
        <tr>
            <td>Get</td>
            <td>`{{ prestige_world_wide:participants }}`</td>
            <td>Returns a string</td>
        </tr>
    </tbody>
</table>

**Example**   

    {{ prestige_world_wide:participants }}

## If the event is full <a id="full"></a>
<table>
    <tbody>
        <tr>
            <td>Check</td>
            <td>`{{ if {prestige_world_wide:is_full} }}{{ /if }}`</td>
            <td>Returns true/false</td>
        </tr>
    </tbody>
</table>

**Example**   

    {{ if {prestige_world_wide:is_full} }}
        It's full :-(
    {{ else }}
        It's not full! :-D
    {{ /if }}

## Export ICS file <a id="ics"></a>
<table>
    <tbody>
        <tr>
            <td>Get</td>
            <td>`{{ prestige_world_wide:icalendar }}`</td>
            <td>Returns a file</td>
        </tr>
    </tbody>
</table>

**Example**   

    <a href="{{ prestige_world_wide:icalendar }}" download title="Download ICS file">Download ICS file</a>

## Add to Google Calendar <a id="gcal"></a>
<table>
    <tbody>
        <tr>
            <td>Get</td>
            <td>`{{ prestige_world_wide:google_calendar }}`</td>
            <td>Returns a url</td>
        </tr>
    </tbody>
</table>

**Example**   

    <a href="{{ prestige_world_wide:google_calendar }}" title="Add to Google Calendar">Google Calendar</a>

## Signup form <a id="form"></a>
If you selected a form you will have to add the code for that form on the event page. More info about adding a form is [in the Statamic docs here](https://docs.statamic.com/forms#main). You don't have to add any extra fields for PW, it does that by itself. __The only requirement is that the form must exist on the event page__. You can use 1 form for all events, or use 1 form for each event. It's up to you. Wrap the form in `{{ if {prestige_world_wide:has_form} }}{{ if !{prestige_world_wide:is_full} }}` to hide it when the event is full.
