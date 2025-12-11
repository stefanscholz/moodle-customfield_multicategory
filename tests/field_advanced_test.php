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
 * Advanced unit tests for multi-category custom field.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_multicategory;

use core_customfield\handler;

/**
 * Advanced unit tests for multi-category custom field.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \customfield_multicategory\field_controller
 * @covers      \customfield_multicategory\data_controller
 */
class field_advanced_test extends \advanced_testcase {

	/**
	 * Get generator
	 *
	 * @return \core_customfield_generator
	 */
	protected function get_generator(): \core_customfield_generator {
		return $this->getDataGenerator()->get_plugin_generator('core_customfield');
	}

	/**
	 * Test multiple multicategory fields on same course
	 */
	public function test_multiple_fields_same_course() {
		$this->resetAfterTest();

		// Create categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Cat1']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Cat2']);
		$cat3 = $this->getDataGenerator()->create_category(['name' => 'Cat3']);

		// Create two custom fields.
		$fieldcategory = $this->get_generator()->create_category();
		$field1 = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'field1',
		]);
		$field2 = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'field2',
		]);

		// Create course and save different data to each field.
		$course = $this->getDataGenerator()->create_course();

		$data1 = \core_customfield\data_controller::create(0, null, $field1);
		$data1->set('instanceid', $course->id);
		$formdata1 = (object)['customfield_field1' => [$cat1->id, $cat2->id]];
		$data1->instance_form_save($formdata1);

		$data2 = \core_customfield\data_controller::create(0, null, $field2);
		$data2->set('instanceid', $course->id);
		$formdata2 = (object)['customfield_field2' => [$cat2->id, $cat3->id]];
		$data2->instance_form_save($formdata2);

		// Reload and verify both fields have correct data.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);

		$this->assertCount(2, $datas);

		$loaded1 = null;
		$loaded2 = null;
		foreach ($datas as $data) {
			if ($data->get_field()->get('shortname') == 'field1') {
				$loaded1 = $data;
			} else if ($data->get_field()->get('shortname') == 'field2') {
				$loaded2 = $data;
			}
		}

		$this->assertNotNull($loaded1);
		$this->assertNotNull($loaded2);

		$cats1 = $loaded1->get_category_ids();
		$this->assertContains($cat1->id, $cats1);
		$this->assertContains($cat2->id, $cats1);
		$this->assertNotContains($cat3->id, $cats1);

		$cats2 = $loaded2->get_category_ids();
		$this->assertNotContains($cat1->id, $cats2);
		$this->assertContains($cat2->id, $cats2);
		$this->assertContains($cat3->id, $cats2);
	}

	/**
	 * Test deep category hierarchy
	 */
	public function test_deep_category_hierarchy() {
		$this->resetAfterTest();

		// Create deep hierarchy.
		$parent = $this->getDataGenerator()->create_category(['name' => 'Level1']);
		$current = $parent;
		for ($i = 2; $i <= 5; $i++) {
			$current = $this->getDataGenerator()->create_category([
				'name' => "Level$i",
				'parent' => $current->id,
			]);
		}

		// Create field with top-level restriction.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'deepcat',
			'configdata' => ['parentcategory' => $parent->id],
		]);

		// Get available categories.
		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		$reflection = new \ReflectionClass($data);
		$method = $reflection->getMethod('get_available_categories');
		$method->setAccessible(true);
		$available = $method->invoke($data);

		// Should have all 5 levels.
		$this->assertCount(5, $available);

		// Verify hierarchical naming.
		$deepestname = $available[$current->id];
		$this->assertStringContainsString('Level1', $deepestname);
		$this->assertStringContainsString('Level5', $deepestname);
		$this->assertEquals(4, substr_count($deepestname, ' / '));
	}

	/**
	 * Test invalid data handling
	 */
	public function test_invalid_data_handling() {
		global $DB;
		$this->resetAfterTest();

		// Create field and course.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'invalid',
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$data->set('value', '');
		$data->save();

		// Manually insert invalid data.
		$DB->set_field('customfield_data', 'value', '999999,abc,1000000', ['id' => $data->get('id')]);

		// Reload and verify it handles gracefully.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);
		$loadeddata = reset($datas);

		$categoryids = $loadeddata->get_category_ids();
		// Should only return valid numeric IDs, even if categories don't exist.
		foreach ($categoryids as $id) {
			$this->assertTrue(is_numeric($id));
		}

		// Export should not fatal error.
		$exported = $loadeddata->export_value();
		$this->assertIsString($exported);
	}

	/**
	 * Test SQL pattern matching for course queries
	 */
	public function test_sql_pattern_matching() {
		global $DB;
		$this->resetAfterTest();

		// Create categories and courses.
		$targetcat = $this->getDataGenerator()->create_category(['name' => 'Target']);
		$othercat = $this->getDataGenerator()->create_category(['name' => 'Other']);

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'sqltest',
		]);

		// Course 1: Real category.
		$course1 = $this->getDataGenerator()->create_course(['category' => $targetcat->id]);

		// Course 2: Virtual category only.
		$course2 = $this->getDataGenerator()->create_course(['category' => $othercat->id]);
		$data2 = \core_customfield\data_controller::create(0, null, $field);
		$data2->set('instanceid', $course2->id);
		$formdata = (object)['customfield_sqltest' => [$targetcat->id]];
		$data2->instance_form_save($formdata);

		// Course 3: Multiple virtual categories including target.
		$course3 = $this->getDataGenerator()->create_course(['category' => $othercat->id]);
		$data3 = \core_customfield\data_controller::create(0, null, $field);
		$data3->set('instanceid', $course3->id);
		$formdata = (object)['customfield_sqltest' => [$othercat->id, $targetcat->id]];
		$data3->instance_form_save($formdata);

		// Course 4: Not in target category at all.
		$course4 = $this->getDataGenerator()->create_course(['category' => $othercat->id]);

		// Query using the pattern that block_dash would use.
		$sql = "SELECT DISTINCT c.id, c.fullname
				FROM {course} c
				LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id 
					AND cfd.fieldid = :fieldid
				WHERE c.category = :categoryid
					OR cfd.value = :exact
					OR " . $DB->sql_like('cfd.value', ':pattern1') . "
					OR " . $DB->sql_like('cfd.value', ':pattern2') . "
				ORDER BY c.id";

		$params = [
			'fieldid' => $field->get('id'),
			'categoryid' => $targetcat->id,
			'exact' => $targetcat->id,
			'pattern1' => $targetcat->id . ',%',
			'pattern2' => '%,' . $targetcat->id . ',%',
		];

		$courses = $DB->get_records_sql($sql, $params);

		// Should find courses 1, 2, and 3, but not 4.
		$this->assertCount(3, $courses);
		$courseids = array_keys($courses);
		$this->assertContains($course1->id, $courseids);
		$this->assertContains($course2->id, $courseids);
		$this->assertContains($course3->id, $courseids);
		$this->assertNotContains($course4->id, $courseids);
	}

	/**
	 * Test course deletion cleanup
	 */
	public function test_course_deletion_cleanup() {
		global $DB;
		$this->resetAfterTest();

		// Create field and course with data.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Cat1']);

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'deletetest',
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$formdata = (object)['customfield_deletetest' => [$cat1->id]];
		$data->instance_form_save($formdata);

		$dataid = $data->get('id');
		$this->assertTrue($DB->record_exists('customfield_data', ['id' => $dataid]));

		// Delete course.
		delete_course($course, false);

		// Verify custom field data is deleted.
		$this->assertFalse($DB->record_exists('customfield_data', ['id' => $dataid]));
	}

	/**
	 * Test special characters in category names
	 */
	public function test_special_characters_in_names() {
		$this->resetAfterTest();

		// Create categories with special characters.
		$cat1 = $this->getDataGenerator()->create_category(['name' => "Cat's & \"Quotes\""]);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Cat<tag>']);
		$cat3 = $this->getDataGenerator()->create_category(['name' => 'Cat/Slash']);

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'specialchars',
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$formdata = (object)['customfield_specialchars' => [$cat1->id, $cat2->id, $cat3->id]];
		$data->instance_form_save($formdata);

		// Reload and verify export handles special chars.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);
		$loadeddata = reset($datas);

		$exported = $loadeddata->export_value();
		$this->assertNotEmpty($exported);
		// Verify it doesn't break HTML.
		$this->assertStringNotContainsString('<tag>', $exported);
	}

	/**
	 * Test empty parent category restriction (should show all)
	 */
	public function test_empty_parent_shows_all() {
		$this->resetAfterTest();

		// Create some categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Cat1']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Cat2']);

		// Create field with no parent restriction.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'noparent',
			'configdata' => ['parentcategory' => 0],
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		$reflection = new \ReflectionClass($data);
		$method = $reflection->getMethod('get_available_categories');
		$method->setAccessible(true);
		$available = $method->invoke($data);

		// Should have all categories including Miscellaneous.
		$this->assertGreaterThanOrEqual(2, count($available));
		$this->assertArrayHasKey($cat1->id, $available);
		$this->assertArrayHasKey($cat2->id, $available);
	}

	/**
	 * Test datafield method returns correct field name
	 */
	public function test_datafield_method() {
		$this->resetAfterTest();

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'test',
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		$this->assertEquals('value', $data->datafield());
	}

	/**
	 * Test field type constant
	 */
	public function test_field_type_constant() {
		$this->assertEquals('multicategory', field_controller::TYPE);
	}

	/**
	 * Test supports_course_deletion method
	 */
	public function test_supports_course_deletion() {
		$this->resetAfterTest();

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'test',
		]);

		$this->assertTrue($field->supports_course_deletion());
	}

	/**
	 * Test with non-existent parent category in config
	 */
	public function test_invalid_parent_category() {
		$this->resetAfterTest();

		// Create field with non-existent parent.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'invalidparent',
			'configdata' => ['parentcategory' => 999999],
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		// Should return empty array gracefully.
		$reflection = new \ReflectionClass($data);
		$method = $reflection->getMethod('get_available_categories');
		$method->setAccessible(true);
		$available = $method->invoke($data);

		$this->assertEmpty($available);
	}

	/**
	 * Test category at end of list (edge case for SQL pattern)
	 */
	public function test_category_at_end_of_list() {
		global $DB;
		$this->resetAfterTest();

		$cat1 = $this->getDataGenerator()->create_category();
		$cat2 = $this->getDataGenerator()->create_category();
		$cat3 = $this->getDataGenerator()->create_category();

		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'endtest',
		]);

		// Save with cat3 at the end.
		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$formdata = (object)['customfield_endtest' => [$cat1->id, $cat2->id, $cat3->id]];
		$data->instance_form_save($formdata);

		// Query for cat3 (at end).
		$sql = "SELECT c.id
				FROM {course} c
				LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id 
					AND cfd.fieldid = :fieldid
				WHERE cfd.value = :exact
					OR " . $DB->sql_like('cfd.value', ':pattern1') . "
					OR " . $DB->sql_like('cfd.value', ':pattern2');

		$params = [
			'fieldid' => $field->get('id'),
			'exact' => $cat3->id,
			'pattern1' => $cat3->id . ',%',
			'pattern2' => '%,' . $cat3->id,
		];

		$result = $DB->get_records_sql($sql, $params);
		$this->assertCount(1, $result);
	}
}