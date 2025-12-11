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
 * Unit tests for multi-category custom field.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_multicategory;

use core_customfield\field_controller;
use core_customfield\handler;

/**
 * Unit tests for multi-category custom field.
 *
 * @package     customfield_multicategory
 * @copyright   2025 bdecent gmbh <https://bdecent.de/>
 * @author      Stefan Scholz <sts@bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \customfield_multicategory\field_controller
 * @covers      \customfield_multicategory\data_controller
 */
class field_test extends \advanced_testcase {

	/**
	 * Get generator
	 *
	 * @return \core_customfield_generator
	 */
	protected function get_generator(): \core_customfield_generator {
		return $this->getDataGenerator()->get_plugin_generator('core_customfield');
	}

	/**
	 * Test field creation
	 */
	public function test_create_field() {
		$this->resetAfterTest();

		$category = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $category->get('id'),
			'type' => 'multicategory',
			'shortname' => 'testmulticat',
			'name' => 'Test Multi-category',
		]);

		$this->assertInstanceOf(field_controller::class, $field);
		$this->assertEquals('multicategory', $field->get('type'));
		$this->assertEquals('testmulticat', $field->get('shortname'));
	}

	/**
	 * Test field with parent category restriction
	 */
	public function test_field_with_parent_restriction() {
		global $DB;
		$this->resetAfterTest();

		// Create category structure.
		$parentcat = $this->getDataGenerator()->create_category(['name' => 'Parent']);
		$childcat1 = $this->getDataGenerator()->create_category(['name' => 'Child 1', 'parent' => $parentcat->id]);
		$childcat2 = $this->getDataGenerator()->create_category(['name' => 'Child 2', 'parent' => $parentcat->id]);
		$othercat = $this->getDataGenerator()->create_category(['name' => 'Other']);

		// Create custom field with parent restriction.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'restrictedcat',
			'configdata' => ['parentcategory' => $parentcat->id],
		]);

		// Create course and get data controller.
		$course = $this->getDataGenerator()->create_course();
		$handler = handler::get_handler('core_course', 'course');
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		// Test that available categories only include parent and children.
		$reflection = new \ReflectionClass($data);
		$method = $reflection->getMethod('get_available_categories');
		$method->setAccessible(true);
		$available = $method->invoke($data);

		$this->assertArrayHasKey($parentcat->id, $available);
		$this->assertArrayHasKey($childcat1->id, $available);
		$this->assertArrayHasKey($childcat2->id, $available);
		$this->assertArrayNotHasKey($othercat->id, $available);
	}

	/**
	 * Test saving and loading category data
	 */
	public function test_save_and_load_data() {
		$this->resetAfterTest();

		// Create categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);
		$cat3 = $this->getDataGenerator()->create_category(['name' => 'Category 3']);

		// Create custom field.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'multicats',
		]);

		// Create course.
		$course = $this->getDataGenerator()->create_course();

		// Create and save data.
		$handler = handler::get_handler('core_course', 'course');
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		// Simulate form save.
		$formdata = (object)[
			'customfield_multicats' => [$cat1->id, $cat2->id, $cat3->id],
		];
		$data->instance_form_save($formdata);

		// Reload and verify.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);
		$loadeddata = reset($datas);

		$this->assertNotEmpty($loadeddata);
		$categoryids = $loadeddata->get_category_ids();
		$this->assertCount(3, $categoryids);
		$this->assertContains($cat1->id, $categoryids);
		$this->assertContains($cat2->id, $categoryids);
		$this->assertContains($cat3->id, $categoryids);
	}

	/**
	 * Test export value
	 */
	public function test_export_value() {
		$this->resetAfterTest();

		// Create categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Alpha']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Beta']);

		// Create custom field.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'exporttest',
		]);

		// Create course and save data.
		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		$formdata = (object)[
			'customfield_exporttest' => [$cat1->id, $cat2->id],
		];
		$data->instance_form_save($formdata);

		// Reload and check export.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);
		$loadeddata = reset($datas);

		$exported = $loadeddata->export_value();
		$this->assertStringContainsString('Alpha', $exported);
		$this->assertStringContainsString('Beta', $exported);
	}

	/**
	 * Test validation with parent restriction
	 */
	public function test_validation_with_restriction() {
		$this->resetAfterTest();

		// Create category structure.
		$parentcat = $this->getDataGenerator()->create_category(['name' => 'Parent']);
		$childcat = $this->getDataGenerator()->create_category(['name' => 'Child', 'parent' => $parentcat->id]);
		$othercat = $this->getDataGenerator()->create_category(['name' => 'Other']);

		// Create restricted field.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'restricted',
			'configdata' => ['parentcategory' => $parentcat->id],
		]);

		// Create course and data.
		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		// Try to save with invalid category (should be filtered out).
		$formdata = (object)[
			'customfield_restricted' => [$childcat->id, $othercat->id],
		];
		$data->instance_form_save($formdata);

		// Verify only valid category was saved.
		$categoryids = $data->get_category_ids();
		$this->assertContains($childcat->id, $categoryids);
		$this->assertNotContains($othercat->id, $categoryids);
	}

	/**
	 * Test empty value handling
	 */
	public function test_empty_value() {
		$this->resetAfterTest();

		// Create custom field.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'emptytest',
		]);

		// Create course.
		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);

		// Test with empty array.
		$formdata = (object)[
			'customfield_emptytest' => [],
		];
		$data->instance_form_save($formdata);

		$this->assertEmpty($data->get_category_ids());
		$this->assertNull($data->export_value());
	}

	/**
	 * Test course backup and restore
	 */
	public function test_backup_restore() {
		global $USER;
		$this->resetAfterTest();
		$this->setAdminUser();

		// Create categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);

		// Create custom field.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'backuptest',
		]);

		// Create course with data.
		$course = $this->getDataGenerator()->create_course();
		$handler = handler::get_handler('core_course', 'course');
		$handler->save_field_configuration($field, (object)['shortname' => 'backuptest']);

		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$formdata = (object)[
			'customfield_backuptest' => [$cat1->id, $cat2->id],
		];
		$data->instance_form_save($formdata);

		// Backup and restore.
		$newcourseid = $this->backup_and_restore($course);

		// Verify data in restored course.
		$datas = $handler->get_instance_data($newcourseid);
		$restoreddata = reset($datas);

		$this->assertNotEmpty($restoreddata);
		$categoryids = $restoreddata->get_category_ids();
		$this->assertCount(2, $categoryids);
		$this->assertContains($cat1->id, $categoryids);
		$this->assertContains($cat2->id, $categoryids);
	}

	/**
	 * Helper function to backup and restore a course
	 *
	 * @param \stdClass $course
	 * @return int new course id
	 */
	protected function backup_and_restore($course) {
		global $USER, $CFG;
		require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
		require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

		// Backup.
		$bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
			\backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);
		$backupid = $bc->get_backupid();
		$bc->execute_plan();
		$bc->destroy();

		// Restore.
		$newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $course->category);
		$rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO,
			\backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
		$rc->execute_precheck();
		$rc->execute_plan();
		$rc->destroy();

		return $newcourseid;
	}

	/**
	 * Test deleted category handling
	 */
	public function test_deleted_category() {
		global $DB;
		$this->resetAfterTest();

		// Create categories.
		$cat1 = $this->getDataGenerator()->create_category(['name' => 'Keep']);
		$cat2 = $this->getDataGenerator()->create_category(['name' => 'Delete']);

		// Create custom field and save data.
		$fieldcategory = $this->get_generator()->create_category();
		$field = $this->get_generator()->create_field([
			'categoryid' => $fieldcategory->get('id'),
			'type' => 'multicategory',
			'shortname' => 'deletetest',
		]);

		$course = $this->getDataGenerator()->create_course();
		$data = \core_customfield\data_controller::create(0, null, $field);
		$data->set('instanceid', $course->id);
		$formdata = (object)[
			'customfield_deletetest' => [$cat1->id, $cat2->id],
		];
		$data->instance_form_save($formdata);

		// Delete one category.
		$cat2obj = \core_course_category::get($cat2->id);
		$cat2obj->delete_full(false);

		// Reload and verify export handles deleted category gracefully.
		$handler = handler::get_handler('core_course', 'course');
		$datas = $handler->get_instance_data($course->id);
		$loadeddata = reset($datas);

		$exported = $loadeddata->export_value();
		$this->assertStringContainsString('Keep', $exported);
		$this->assertStringNotContainsString('Delete', $exported);
	}
}