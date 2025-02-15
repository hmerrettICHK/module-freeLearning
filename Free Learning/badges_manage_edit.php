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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Free Learning/badges_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs
         ->add(__m('Manage Badges'), 'badges_manage.php')
         ->add(__m('Edit Badges'));

    if (isModuleAccessible($guid, $connection2, '/modules/Badges/badges_manage.php') == false) {
        //Acess denied
        echo "<div class='error'>";
        echo __($guid, 'This functionality requires the Badges module to be installed, active and available.', 'Free Learning');
        echo '</div>';
    } else {
        //Acess denied
        echo "<div class='success'>";
        echo 'The Badges module is installed, active and available, so you can access this functionality.';
        echo '</div>';

        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, null);
        }

        //Check if school year specified
        $freeLearningBadgeID = $_GET['freeLearningBadgeID'];
        if ($freeLearningBadgeID == '') {
            echo "<div class='error'>";
            echo __($guid, 'You have not specified one or more required parameters.');
            echo '</div>';
        } else {
            try {
                $data = array('freeLearningBadgeID' => $freeLearningBadgeID);
                $sql = 'SELECT freeLearningBadge.*, name, category, logo, description
                    FROM freeLearningBadge
                        JOIN badgesBadge ON (freeLearningBadge.badgesBadgeID=badgesBadge.badgesBadgeID)
                    WHERE freeLearningBadgeID=:freeLearningBadgeID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result->rowCount() != 1) {
                echo "<div class='error'>";
               echo __($guid, 'The specified record cannot be found.');
                echo '</div>';
            } else {
                //Let's go!
                $values = $result->fetch();

                if ($_GET['search'] != '') {
                    echo "<div class='linkTop'>";
                    echo "<a href='".$gibbon->session->get('absoluteURL').'/index.php?q=/modules/Free Learning/badges_manage.php&search='.$_GET['search']."'>".__($guid, 'Back to Search Results')."</a>";
                    echo '</div>';
                }

                $form = Form::create('action', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module']."/badges_manage_editProcess.php?freeLearningBadgeID=$freeLearningBadgeID");

                $form->addHiddenValue('address', $_SESSION[$guid]['address']);
                $form->addHiddenValue('q', "/modules/".$_SESSION[$guid]['module']."/badges_manage_add.php");

                $data = array();
                $sql = "SELECT badgesBadgeID AS value, name, category AS groupBy FROM badgesBadge WHERE active='Y' ORDER BY category, name";
                $row = $form->addRow();
                    $row->addLabel('badgesBadgeID', __('Badge'));
                    $row->addSelect('badgesBadgeID')->fromQuery($pdo, $sql, $data, 'groupBy')->placeholder()->required();

                $row = $form->addRow();
                    $row->addLabel('active', __('Active'));
                    $row->addYesNo('active')->required();

                $row = $form->addRow()->addHeading(__m('Conditions'))
                    ->append(__m('This award will automatically be awarded on unit completion, if all of the following conditions are met. Fields left blank will be disregarded.'));

                $row = $form->addRow();
                    $row->addLabel('unitsCompleteTotal', __m('Units Completed - Total'))->description(__m('Enter a number greater than zero, or leave blank.'));
                    $row->addNumber('unitsCompleteTotal')->decimalPlaces(0)->minimum(1)->maximum(999)->maxLength(3);

                $row = $form->addRow();
                    $row->addLabel('unitsCompleteThisYear', __m('Units Completed - This Year'))->description(__m('Enter a number greater than zero, or leave blank.'));
                    $row->addNumber('unitsCompleteThisYear')->decimalPlaces(0)->minimum(1)->maximum(999)->maxLength(3);

                $row = $form->addRow();
                    $row->addLabel('unitsCompleteDepartmentCount', __m('Units Completed - Department Spread'))->description(__m('Enter a number greater than zero, or leave blank.'));
                    $row->addNumber('unitsCompleteDepartmentCount')->decimalPlaces(0)->minimum(1)->maximum(999)->maxLength(3);

                $row = $form->addRow();
                    $row->addLabel('unitsCompleteIndividual', __m('Units Completed - Individual'))->description(__m('Enter a number greater than zero, or leave blank.'));
                    $row->addNumber('unitsCompleteIndividual')->decimalPlaces(0)->minimum(1)->maximum(999)->maxLength(3);

                $row = $form->addRow();
                    $row->addLabel('unitsCompleteGroup', __m('Units Completed - Group'))->description(__m('Enter a number greater than zero, or leave blank.'));
                    $row->addNumber('unitsCompleteGroup')->decimalPlaces(0)->minimum(1)->maximum(999)->maxLength(3);

                $difficultyOptions = getSettingByScope($connection2, 'Free Learning', 'difficultyOptions');
                $difficultyOptions = ($difficultyOptions != false) ? explode(',', $difficultyOptions) : [];
                $difficulties = [];
                foreach ($difficultyOptions as $difficultyOption) {
                    $difficulties[$difficultyOption] = __m($difficultyOption);
                }
                $row = $form->addRow();
                    $row->addLabel('difficultyLevelMaxAchieved', __m('Difficulty Level Threshold'));
                    $row->addSelect('difficultyLevelMaxAchieved')->fromArray($difficulties)->placeholder();

                $sql = 'SELECT freeLearningUnitID as value, name FROM freeLearningUnit ORDER BY name';
                $row = $form->addRow();
                    $row->addLabel('specificUnitsComplete', __m('Specific Unit Completion'))->description('Completing any of the selected units will grant badge.');
                    $row->addSelect('specificUnitsComplete')->fromQuery($pdo, $sql, array())->selectMultiple()->loadFromCSV($values);

                $form->loadAllValuesFrom($values);

                $row = $form->addRow();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }
}
?>
