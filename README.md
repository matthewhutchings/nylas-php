# Nylas PHP

**Forked from [nylas/nylas-php](https://github.com/nylas/nylas-php). Adapted for [Activix](https://activix.ca).**

PHP bindings for the Nylas REST API [https://www.nylas.com](https://www.nylas.com).



## Installation

You can install the library by running:

```php
cd nylas-php
composer install
```



## Usage

The Nylas REST API uses server-side (three-legged) OAuth, and this library provides convenience methods to simplify the OAuth process. Here's how it works:

1. You redirect the user to our login page, along with your App Id and Secret
1. Your user logs in
1. She is redirected to a callback URL of your own, along with an access code
1. You use this access code to get an authorization token to the API

For more information about authenticating with Nylas, visit the [Developer Documentation](https://www.nylas.com/docs/gettingstarted-hosted#authenticating).

In practice, the Nylas REST API client simplifies this down to two steps.



## Fetching Account Information

```php
$client = new Nylas(CLIENT, SECRET, TOKEN);
$account = $client->account();

echo $account->email_address;
echo $account->provider;
```



## Fetching Threads

```php
$client = new Nylas(CLIENT, SECRET, TOKEN);

// Fetch the first thread
$firstThread = $client->threads()->first();
echo $firstThread->id;

// Fetch first 2 latest threads
$twoThreads = $client->threads()->all(2);

foreach ($twoThreads as $thread) {
    echo $thread->id;
}

// List all threads with 'ben@nylas.com'
$searchCriteria = ['any_email' => 'ben@nylas.com'];
$getThreads = $client->threads()->where($searchCriteria)->items();

foreach ($getThreads as $thread) {
    echo $thread->id;
}
```



## Working with Threads

```php
// List thread participants
foreach ($thread->participants as $participant) {
    echo $participant->email;
    echo $participant->name;
}

// Mark as Read
$thread->markAsRead();

// Mark as Seen
$thread->markAsSeen();

// Archive
$thread->archive();

// Unarchive
$thread->unarchive();

// Trash
$thread->trash();

// Star
$thread->star();

// Unstar
$thread->unstar();

// Add or remove arbitrary tags
$toAdd = ['cfa1233ef123acd12'];
$toRemove = ['inbox'];
$thread->addTags($toAdd);
$thread->removeTags($toRemove);

// Listing messages
foreach ($thread->messages()->items() as $message) {
    echo $message->subject;
    echo $message->body;
}
```



## Working with Files

```php
$client = new Nylas(CLIENT, SECRET, TOKEN);

$filePath = '/var/my/folder/test_file.pdf';
$uploadResp = $client->files()->create($filePath);
echo $uploadResp->id;
```



## Working with Drafts

```php
$client = new Nylas(CLIENT, SECRET, TOKEN);

$personObj = new \Nylas\Models\Person('Kartik Talwar', 'kartik@nylas.com');

$messageObj = [
    'to' => [$personObj],
    'subject' => 'Hello, PHP!',
    'body' => 'Test <br> message'
];

$draft = $client->drafts()->create($messageObj);
$sendMessage = $draft->send();
echo $sendMessage->id;
```



## Working with Events

```php
$client = new Nylas(CLIENT, SECRET, TOKEN);
$calendars = $client->calendars()->all();
$calendar = null;

foreach ($calendars as $i) {
    if (!$i->read_only) {
        $calendar = $i;
    }
}

$personObj = new \Nylas\Models\Person('Kartik Talwar', 'kartik@nylas.com');

$calendarObj = [
    'title' => 'Important Meeting',
    'location' => 'Nylas HQ',
    'participants' => [$personObj],
    'calendar_id' => $calendar->id,
    'when' => [
        'start_time' => time(),
        'end_time' => time() + (30 * 60),
    ],
];

// Create event
$event = $client->events()->create($calendarObj);
echo $event->id;

// Update
$event = $event->update(['location' => 'Meeting room #1']);

// Delete event
$event->delete();

// Delete event (alternate)
$remove = $client->events()->find($event->id)->delete();
```
