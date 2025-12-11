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
 * Field controller for multi-category custom field type.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_multicategory;

defined('MOODLE_INTERNAL') || die();

/**
 * Class field_controller
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {

	/**
	 * Plugin type
	 */
	const TYPE = 'multicategory';

	/**
	 * Add fields for editing a multicategory field.
	 *
	 * @param \MoodleQuickForm $mform
	 */
	public function config_form_definition(\MoodleQuickForm $mform) {
		$mform->addElement('header', 'header_specificsettings',
			get_string('specificsettings', 'customfield_multicategory'));
		$mform->setExpanded('header_specificsettings', true);

		// Get all categories for parent selection.
		$categories = \core_course_category::make_categories_list('', 0, ' / ');
		$categories = [0 => get_string('none')] + $categories;

		$mform->addElement('autocomplete', 'configdata[parentcategory]',
			get_string('parentcategory', 'customfield_multicategory'),
			$categories);
		$mform->addHelpButton('configdata[parentcategory]', 'parentcategory', 'customfield_multicategory');
	}

	/**
	 * Validate the data from the config form.
	 *
	 * @param array $data from the add/edit profile field form
	 * @param array $files
	 * @return array associative array of error messages
	 */
	public function config_form_validation(array $data, $files = []) : array {
		$errors = parent::config_form_validation($data, $files);

		if (!empty($data['configdata']['parentcategory'])) {
			// Validate that the category exists.
			try {
				\core_course_category::get($data['configdata']['parentcategory'], MUST_EXIST);
			} catch (\Exception $e) {
				$errors['configdata[parentcategory]'] = get_string('errorinvalidcategory', 'customfield_multicategory');
			}
		}

		return $errors;
	}

	/**
	 * Does this field support course deletion?
	 *
	 * @return bool
	 */
	public function supports_course_deletion() : bool {
		return true;
	}
}