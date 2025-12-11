<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Data controller for multi-category custom field type.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_multicategory;

defined('MOODLE_INTERNAL') || die();

/**
 * Class data_controller
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller {

	/**
	 * Return the name of the field where the information is stored
	 *
	 * @return string
	 */
	public function datafield() : string {
		return 'value';
	}

	/**
	 * Add fields for editing a multicategory field.
	 *
	 * @param \MoodleQuickForm $mform
	 */
	public function instance_form_definition(\MoodleQuickForm $mform) {
		$field = $this->get_field();
		$config = $field->get('configdata');
		$elementname = $this->get_form_element_name();

		// Get available categories based on parent restriction.
		$categories = $this->get_available_categories();

		if (empty($categories)) {
			$mform->addElement('static', $elementname . '_nocats',
				$this->get_field()->get_formatted_name(),
				get_string('nocategories', 'customfield_multicategory'));
			return;
		}

		$options = [
			'multiple' => true,
			'noselectionstring' => get_string('none'),
		];

		$mform->addElement('autocomplete', $elementname,
			$this->get_field()->get_formatted_name(),
			$categories,
			$options);

		$mform->setType($elementname, PARAM_SEQUENCE);

		// Set default value from saved data.
		$value = $this->get_value();
		if (!empty($value)) {
			$categoryids = $this->get_category_ids();
			$mform->setDefault($elementname, $categoryids);
		}
	}

	/**
	 * Get available categories based on parent restriction
	 *
	 * @return array
	 */
	protected function get_available_categories() : array {
		$field = $this->get_field();
		$config = $field->get('configdata');
		$parentcategoryid = $config['parentcategory'] ?? 0;

		if (empty($parentcategoryid)) {
			// No restriction, return all categories.
			return \core_course_category::make_categories_list('', 0, ' / ');
		}

		// Get parent category and all its descendants.
		try {
			$parentcategory = \core_course_category::get($parentcategoryid, MUST_EXIST);

			// Get all subcategories recursively.
			$categories = [];
			$this->add_category_and_children($parentcategory, $categories, '');

			return $categories;
		} catch (\Exception $e) {
			// Parent category not found, return empty.
			return [];
		}
	}

	/**
	 * Recursively add category and its children to the list
	 *
	 * @param \core_course_category $category
	 * @param array $categories
	 * @param string $prefix
	 */
	protected function add_category_and_children($category, &$categories, $prefix) {
		$categories[$category->id] = $prefix . $category->name;

		$children = $category->get_children();
		foreach ($children as $child) {
			$this->add_category_and_children($child, $categories, $prefix . $category->name . ' / ');
		}
	}

	/**
	 * Returns the default value as defined in the field settings.
	 *
	 * @return mixed
	 */
	public function get_default_value() {
		return '';
	}

	/**
	 * Saves the data coming from form
	 *
	 * @param \stdClass $datanew data coming from the form
	 */
	public function instance_form_save(\stdClass $datanew) {
		$fieldname = $this->get_form_element_name();

		if (!property_exists($datanew, $fieldname)) {
			return;
		}

		$value = $datanew->$fieldname;

		if (is_array($value)) {
			// Validate categories if parent restriction is set.
			$field = $this->get_field();
			$config = $field->get('configdata');
			$parentcategoryid = $config['parentcategory'] ?? 0;

			if ($parentcategoryid) {
				$value = $this->validate_categories($value, $parentcategoryid);
			}

			$value = implode(',', $value);
		} else {
			$value = '';
		}

		// Set internal and charvalue based on data type.
		$this->set($this->datafield(), $value);
		$this->set('valueformat', FORMAT_PLAIN);
		$this->save();
	}

	/**
	 * Validate that selected categories are within allowed parent
	 *
	 * @param array $categoryids
	 * @param int $parentcategoryid
	 * @return array validated category IDs
	 */
	protected function validate_categories(array $categoryids, int $parentcategoryid) : array {
		$allowedcategories = $this->get_available_categories();
		$validated = [];

		foreach ($categoryids as $catid) {
			if (array_key_exists($catid, $allowedcategories)) {
				$validated[] = $catid;
			}
		}

		return $validated;
	}

	/**
	 * Returns the value as it is stored in the database or default value if data record does not exist
	 *
	 * @return mixed
	 */
	public function get_value() {
		if (!$this->get('id')) {
			return '';
		}
		return $this->get($this->datafield());
	}

	/**
	 * Returns value in a human-readable format
	 *
	 * @return mixed|null value or null if empty
	 */
	public function export_value() {
		$value = $this->get_value();

		if (empty($value)) {
			return null;
		}

		$categoryids = explode(',', $value);
		$categorynames = [];

		foreach ($categoryids as $catid) {
			if (empty($catid)) {
				continue;
			}

			try {
				$category = \core_course_category::get($catid, IGNORE_MISSING);
				if ($category) {
					$categorynames[] = $category->get_formatted_name();
				}
			} catch (\Exception $e) {
				// Category no longer exists, skip it.
				continue;
			}
		}

		return !empty($categorynames) ? implode(', ', $categorynames) : null;
	}

	/**
	 * Get category IDs as array
	 *
	 * @return array
	 */
	public function get_category_ids() : array {
		$value = $this->get_value();

		if (empty($value) || !is_string($value)) {
			return [];
		}

		return array_filter(explode(',', $value), function($id) {
			return !empty($id) && is_numeric($id);
		});
	}
}