@customfield @customfield_multicategory
Feature: Multi-category custom field
  In order to associate courses with multiple categories
  As an admin
  I need to be able to add a multi-category custom field

  Background:
	Given the following "custom field categories" exist:
	  | name              | component   | area   | itemid |
	  | Course fields     | core_course | course | 0      |
	And the following "categories" exist:
	  | name        | category | idnumber |
	  | Category A  | 0        | CATA     |
	  | Category B  | 0        | CATB     |
	  | Category C  | 0        | CATC     |
	  | Sub Cat A1  | CATA     | CATA1    |
	And the following "custom fields" exist:
	  | name          | category      | type          | shortname |
	  | Multi Cat     | Course fields | multicategory | multicat  |

  @javascript
  Scenario: Create a course with multiple categories
	Given I log in as "admin"
	And I am on site homepage
	And I navigate to "Courses > Add a new course" in site administration
	When I set the following fields to these values:
	  | Course full name  | Test Course |
	  | Course short name | TC1         |
	And I expand all fieldsets
	And I set the field "Multi Cat" to "Category A,Category B"
	And I press "Save and display"
	Then I should see "Test Course"
	When I navigate to "Edit settings" in current page administration
	And I expand all fieldsets
	Then the field "Multi Cat" matches value "Category A,Category B"

  @javascript
  Scenario: Parent category restriction works
	Given the following "custom fields" exist:
	  | name              | category      | type          | shortname | configdata                    |
	  | Restricted Cat    | Course fields | multicategory | restricted| {"parentcategory":"CATA"}    |
	And I log in as "admin"
	And I am on site homepage
	And I navigate to "Courses > Add a new course" in site administration
	When I set the following fields to these values:
	  | Course full name  | Test Course 2 |
	  | Course short name | TC2           |
	And I expand all fieldsets
	Then I should see "Sub Cat A1" in the "Restricted Cat" "field"
	And I should not see "Category B" in the "Restricted Cat" "field"