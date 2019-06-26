<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\FreeLearning\Domain\UnitGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Free Learning/units_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $page->breadcrumbs->add(__m('Manage Units'));
        
        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, null);
        }

        $gibbonDepartmentID = $_GET['gibbonDepartmentID'] ?? '';
        $difficulty = $_GET['difficulty'] ?? '';
        $name = $_GET['name'] ?? '';

        // QUERY
        $unitGateway = $container->get(UnitGateway::class);
        $criteria = $unitGateway->newQueryCriteria()
            ->searchBy($unitGateway->getSearchableColumns(), $name)
            ->sortBy('name')
            ->filterBy('department', $gibbonDepartmentID)
            ->filterBy('difficulty', $difficulty)
            ->filterBy('showInactive', 'Y')
            ->fromPOST();

        // FORM
        $form = Form::create('filter', $gibbon->session->get('absoluteURL').'/index.php', 'get');
        $form->setTitle(__('Filter'));

        $form->setClass('noIntBorder fullWidth');
        $form->addHiddenValue('q', '/modules/Free Learning/units_manage.php');
    
        $learningAreas = $unitGateway->selectLearningAreasAndCourses();
        $row = $form->addRow();
            $row->addLabel('gibbonDepartmentID', __('Learning Area & Course'));
            $row->addSelect('gibbonDepartmentID')->fromResults($learningAreas, 'groupBy')->selected($gibbonDepartmentID)->placeholder();

        $difficulties = array_map('trim', explode(',', getSettingByScope($connection2, 'Free Learning', 'difficultyOptions')));
        $row = $form->addRow();
            $row->addLabel('difficulty', __('Difficulty'));
            $row->addSelect('difficulty')->fromArray($difficulties)->selected($difficulty)->placeholder();

        $row = $form->addRow();
            $row->addLabel('name', __('Name'));
            $row->addTextField('name')->setValue($criteria->getSearchText());

        $row = $form->addRow();
            $row->addSearchSubmit($gibbon->session, __('Clear Filters'));
        
        echo $form->getOutput();


        if ($highestAction == 'Manage Units_all') {
            $units = $unitGateway->queryAllUnits($criteria, $gibbon->session->get('gibbonPersonID'));
        } else {
            $units = $unitGateway->queryUnitsByLearningAreaStaff($criteria, $gibbon->session->get('gibbonPersonID'));
        }

        // DATA TABLE
        $table = DataTable::createPaginated('units', $criteria);
        $table->setTitle(__('View'));

        $table->addHeaderAction('add', __('Add'))
            ->addParam('gibbonDepartmentID', $gibbonDepartmentID)
            ->addParam('difficulty', $difficulty)
            ->addParam('name', $name)
            ->setURL('/modules/Free Learning/units_manage_add.php')
            ->displayLabel();
            
        $table->modifyRows(function ($unit, $row) {
            if ($unit['active'] != 'Y') $row->addClass('error');
            return $row;
        });

        $table->addMetaData('filterOptions', [
            'showInactive:Y'  => __('Show Inactive'),
            'active:Y'        => __('Active').': '.__('Yes'),
            'active:N'        => __('Active').': '.__('No'),
            'access:students' => __m('Available To Students'),
            'access:staff'    => __m('Available To Staff'),
            'access:parents'  => __m('Available To Parents'),
            'access:other'    => __m('Available To Other'),
        ]);

        $table->addColumn('name', __('Name'));
        $table->addColumn('difficulty', __m('Difficulty'));
        $table->addColumn('learningArea', __m('Learning Areas'))
            ->format(function ($unit) {
                return !empty($unit['learningArea'])
                    ? $unit['learningArea']
                    : Format::small(__('None'));
            });
        $table->addColumn('active', __('Active'))->format(Format::using('yesNo', 'active'));

        // ACTIONS
        $canBrowseUnits = isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse.php');
        $table->addActionColumn()
            ->addParam('gibbonDepartmentID', $gibbonDepartmentID)
            ->addParam('difficulty', $difficulty)
            ->addParam('name', $name)
            ->addParam('freeLearningUnitID')
            ->format(function ($unit, $actions) use ($canBrowseUnits) {
                if ($canBrowseUnits) {
                    $actions->addAction('view', __('View'))
                        ->addParam('sidebar', 'true')
                        ->addParam('showInactive', 'Y')
                        ->setURL('/modules/Free Learning/units_browse_details.php');
                }

                $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/Free Learning/units_manage_edit.php');

                $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/Free Learning/units_manage_delete.php');
            });

        echo $table->render($units);
    }
}
