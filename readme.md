# Multi-Category Custom Field

A Moodle custom field plugin that allows courses to be associated with multiple categories.

## Features

- Select multiple categories for a course via course settings
- Optional parent category restriction to limit available categories
- Integrates seamlessly with Moodle's custom field system
- Automatically included in course backup/restore
- Uses existing Moodle categories - no additional category management needed

## Installation

1. Copy the plugin to `customfield/multicategory`
2. Visit Site administration > Notifications to install
3. Go to Site administration > Courses > Course custom fields
4. Add a new "Multi-category" field to your custom field category

## Configuration

When creating the custom field:
- **Parent category restriction**: Optionally select a parent category to restrict choices to only categories within that parent (and its subcategories)

## Usage

### For Course Editors
1. Edit a course
2. Scroll to the custom fields section
3. Select additional categories for the course using the autocomplete field
4. Save the course

### For Developers

Get courses in a category (including virtually assigned ones):
```php
// Get the multicategory custom field
$handler = \core_customfield\handler::get_handler('core_course', 'course');
$fields = $handler->get_fields();

$multicategoryfield = null;
foreach ($fields as $field) {
	if ($field->get('type') == 'multicategory') {
		$multicategoryfield = $field;
		break;
	}
}

if ($multicategoryfield) {
	// Query courses in category (real OR virtual)
	$sql = "SELECT DISTINCT c.*
			FROM {course} c
			LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id 
				AND cfd.fieldid = :fieldid
			WHERE c.category = :categoryid
				OR " . $DB->sql_like('cfd.value', ':pattern1') . "
				OR " . $DB->sql_like('cfd.value', ':pattern2') . "
				OR cfd.value = :exact";
	
	$params = [
		'fieldid' => $multicategoryfield->get('id'),
		'categoryid' => $targetcategoryid,
		'pattern1' => $targetcategoryid . ',%',
		'pattern2' => '%,' . $targetcategoryid . ',%',
		'exact' => $targetcategoryid
	];
	
	$courses = $DB->get_records_sql($sql, $params);
}

// Get virtual categories for a specific course
$handler = \core_customfield\handler::get_handler('core_course', 'course');
$data = $handler->get_instance_data($courseid);
foreach ($data as $d) {
	if ($d->get_field()->get('type') == 'multicategory') {
		$categoryids = $d->get_category_ids();
		// Returns array of category IDs
	}
}
```

## Requirements

- Moodle 4.1 or higher
- PHP 7.4 or higher

## Author

**bdecent gmbh**  
Stefan Scholz <sts@bdecent.de>  
https://bdecent.de

## License

GNU GPL v3 or later

## Version

1.0 (Beta)