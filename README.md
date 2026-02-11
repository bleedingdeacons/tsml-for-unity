# TSML for Unity

A WordPress plugin that integrates [12 Step Meeting List (TSML)](https://github.com/code4recovery/12-step-meeting-list) with the [Unity](https://github.com/bleeding-deacons/unity) plugin.

## Description

TSML for Unity provides a `TsmlMeetingFactory` class that implements Unity's `MeetingFactory`. This allows Unity to create `Meeting` objects from TSML meeting data format.

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- [Unity plugin](https://github.com/bleeding-deacons/unity) installed and activated

## Installation

### Via Composer

```bash
composer require bleeding-deacons/tsml-for-unity
```

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/tsml-for-unity/`
3. Ensure the Unity plugin is installed and activated
4. Activate TSML for Unity through the 'Plugins' menu in WordPress

## Usage

### Getting the Factory Instance

```php
use function TsmlForUnity\get_meeting_factory;

$factory = get_meeting_factory();

if ($factory !== null) {
    $meeting = $factory->createFromSource($tsmlMeetingData);
}
```

### Creating Meetings from TSML Data

```php
use TsmlForUnity\TsmlMeetingFactory;

$factory = new TsmlMeetingFactory();

// TSML meeting data format
$source = [
    'id' => 123,
    'name' => 'Morning Serenity',
    'slug' => 'morning-serenity',
    'location' => 'Community Center',
    'day' => 1, // Monday (0=Sunday, 6=Saturday)
    'time' => '07:00',
    'end_time' => '08:00',
    'types' => ['O', 'D', 'X'], // Open, Discussion, Wheelchair Access
    'attendance_option' => 'in_person',
];

$meeting = $factory->createFromSource($source);

if ($meeting !== null) {
    echo $meeting->getName(); // "Morning Serenity"
    echo $meeting->getDayOfWeek(); // "Monday"
    print_r($meeting->getTypes()); // ["Open", "Discussion", "Wheelchair Access"]
}
```

### Meeting Types

The factory automatically converts TSML type codes to human-readable names:

```php
$factory = new TsmlMeetingFactory();

// Get type name from code
$name = $factory->getTypeName('O'); // "Open"
$name = $factory->getTypeName('D'); // "Discussion"

// Get type code from name
$code = $factory->getTypeCode('Closed'); // "C"

// Get all types
$allTypes = $factory->getAllTypes(); // ['12x12' => '12 Steps & 12 Traditions', ...]
```

### Day Names

```php
$factory = new TsmlMeetingFactory();

$dayName = $factory->getDayName(0); // "Sunday"
$dayName = $factory->getDayName(1); // "Monday"
```

## Hooks

### Actions

- `tsml_for_unity/loaded` - Fires when the plugin is fully loaded and Unity is available

### Filters

None currently.

## Integration with Unity

When Unity's dependency container is initialized, TSML for Unity automatically registers itself:

```php
// Unity will use TsmlMeetingFactory when resolving MeetingFactory
$factory = $container->get('Unity\Meetings\Interfaces\MeetingFactory');
```

## Supported Meeting Types

| Code | Name |
|------|------|
| 12x12 | 12 Steps & 12 Traditions |
| O | Open |
| C | Closed |
| D | Discussion |
| SP | Speaker |
| ST | Step Study |
| B | Big Book |
| M | Men |
| W | Women |
| LGBTQ | LGBTQ |
| Y | Young People |
| X | Wheelchair Access |
| VM | Virtual Meeting |
| ... | [See full list in source] |

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

Developed by [The Bleeding Deacons](https://github.com/bleeding-deacons)
