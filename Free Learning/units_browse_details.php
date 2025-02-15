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

use Gibbon\View\View;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\DiscussionGateway;
use Gibbon\Module\FreeLearning\Domain\UnitGateway;
use Gibbon\Module\FreeLearning\Domain\UnitStudentGateway;
use Gibbon\Module\FreeLearning\Domain\UnitOutcomeGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$publicUnits = getSettingByScope($connection2, 'Free Learning', 'publicUnits');
$canManage = isActionAccessible($guid, $connection2, '/modules/Free Learning/units_manage.php');
$browseAll = isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse.php', 'Browse Units_all');

if (!(isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse.php') == true or ($publicUnits == 'Y' and !$gibbon->session->exists('username')))) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    if ($publicUnits == 'Y' and !$gibbon->session->exists('username')) {
        $highestAction = 'Browse Units_all';
        $highestActionManage = null;
        $roleCategory = null ;
    } else {
        $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
        $roleCategory = getRoleCategory($gibbon->session->get('gibbonRoleIDCurrent'), $connection2);
    }
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Get params
        $freeLearningUnitID = $_GET['freeLearningUnitID'] ?? '';

        $showInactive = $canManage && isset($_GET['showInactive'])
            ? $_GET['showInactive']
            : 'N';
        $gibbonDepartmentID = $_GET['gibbonDepartmentID'] ?? '';
        $difficulty = $_GET['difficulty'] ?? '';
        $name = $_GET['name'] ?? '';

        $view = $_GET['view'] ?? 'list';
        if ($view != 'grid' and $view != 'map') {
            $view = 'list';
        }

        $gibbonPersonID = ($canManage)
            ? ($_GET['gibbonPersonID'] ?? $gibbon->session->get('gibbonPersonID') ?? null)
            : $gibbon->session->get('gibbonPersonID') ?? null;

        $urlParams = compact('showInactive', 'gibbonDepartmentID', 'difficulty', 'name', 'view', 'gibbonPersonID', 'freeLearningUnitID');

        //Breadcrumbs
        if ($roleCategory == null) {
            $page->breadcrumbs
                ->add(__m('Browse Units'), '/modules/Free Learning/units_browse.php', $urlParams)
                ->add(__m('Unit Details'));
        } else {
            $page->breadcrumbs
                ->add(__m('Browse Units'), 'units_browse.php', $urlParams)
                ->add(__m('Unit Details'));
        }

        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, [
                'error6' => __('An error occured with your submission, most likely because a submitted file was too large.'),
            ]);
        }

        if ($freeLearningUnitID == '') {
            echo "<div class='error'>";
            echo __('You have not specified one or more required parameters.');
            echo '</div>';
        } else {
            try {
                $unitList = getUnitList($connection2, $guid, $gibbonPersonID, $roleCategory, $highestAction, null, null, null, $showInactive, $publicUnits, $freeLearningUnitID, null);
                $data = $unitList[0];
                $sql = $unitList[1];
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result->rowCount() != 1) {
                echo "<div class='error'>";
                echo __('The selected record does not exist, or you do not have access to it.');
                echo '</div>';
            } else {
                $values = $result->fetch();
                if ($gibbonDepartmentID != '' or $difficulty != '' or $name != '') {
                    echo "<div class='linkTop'>";
                    echo "<a href='".$gibbon->session->get('absoluteURL')."/index.php?q=/modules/Free Learning/units_browse.php&gibbonDepartmentID=$gibbonDepartmentID&difficulty=$difficulty&name=$name&showInactive=$showInactive&gibbonPersonID=$gibbonPersonID&view=$view'>".__('Back to Search Results', 'Free Learning').'</a>';
                    echo '</div>';
                }

                $prerequisitesMet = false;
                if ($highestAction == 'Browse Units_all') {
                    $prerequisitesMet = true;
                } elseif ($highestAction == 'Browse Units_prerequisites') {
                    if ($values['freeLearningUnitIDPrerequisiteList'] == null or $values['freeLearningUnitIDPrerequisiteList'] == '') {
                        $prerequisitesMet = true;
                    } else {
                        $prerequisitesActive = prerequisitesRemoveInactive($connection2, $values['freeLearningUnitIDPrerequisiteList']);
                        $prerequisitesMet = prerequisitesMet($connection2, $gibbonPersonID, $prerequisitesActive);
                    }
                }

                //Let's go!
                if ($canManage || $browseAll) {
                    echo "<div class='linkTop'>";
                    if ($canManage) {
                        echo "<a href='".$gibbon->session->get('absoluteURL')."/index.php?q=/modules/Free Learning/units_manage_edit.php&freeLearningUnitID=$freeLearningUnitID&gibbonDepartmentID=$gibbonDepartmentID&difficulty=$difficulty&name=$name&showInactive=$showInactive&gibbonPersonID=$gibbonPersonID&view=$view'>".__('Edit')."<img style='margin: 0 0 -4px 3px' title='".__('Edit')."' src='./themes/".$gibbon->session->get('gibbonThemeName')."/img/config.png'/></a>";
                    }
                    if ($canManage & $browseAll) {
                        echo " | ";
                    }
                    if ($browseAll) {
                        echo "<a target='_blank' href='".$gibbon->session->get('absoluteURL')."/modules/Free Learning/units_browse_details_export.php?freeLearningUnitID=$freeLearningUnitID'>".__('Download')."<img style='margin: 0 0 -4px 3px' title='".__('Download')."' src='./themes/".$gibbon->session->get('gibbonThemeName')."/img/download.png'/></a> | ";

                        echo "<a target='_blank' href='".$gibbon->session->get('absoluteURL')."/modules/Free Learning/units_manageProcessBulk.php?action=Export&freeLearningUnitID=$freeLearningUnitID&name=".$values['name']."'>".__('Export')."<img style='margin: 0 0 -4px 3px' title='".__('Export')."' src='./themes/".$gibbon->session->get('gibbonThemeName')."/img/delivery2.png'/></a>";
                    }
                    echo '</div>';
                }

                //Get enrolment Details
                try {
                    $dataEnrol = array('freeLearningUnitID' => $freeLearningUnitID, 'gibbonPersonID' => $gibbonPersonID);
                    $sqlEnrol = 'SELECT freeLearningUnitStudent.*, gibbonPerson.surname, gibbonPerson.email, gibbonPerson.preferredName
                        FROM freeLearningUnitStudent
                        LEFT JOIN gibbonPerson ON (freeLearningUnitStudent.gibbonPersonIDSchoolMentor=gibbonPerson.gibbonPersonID)
                        WHERE freeLearningUnitStudent.freeLearningUnitID=:freeLearningUnitID
                            AND gibbonPersonIDStudent=:gibbonPersonID
                            AND (freeLearningUnitStudent.status=\'Current\' OR freeLearningUnitStudent.status=\'Current - Pending\' OR freeLearningUnitStudent.status=\'Complete - Pending\' OR freeLearningUnitStudent.status=\'Evidence Not Yet Approved\')';
                    $resultEnrol = $connection2->prepare($sqlEnrol);
                    $resultEnrol->execute($dataEnrol);
                } catch (PDOException $e) {
                    echo "<div class='error'>".$e->getMessage().'</div>';
                }

                $rowEnrol = null;
                if ($resultEnrol->rowCount()==1) { //ENROL NOW
                    $rowEnrol = $resultEnrol->fetch() ;
                }


                // UNIT DETAILS TABLE
                $table = DataTable::createDetails('unitDetails');

                $table->addColumn('name', '')->addClass('text-lg font-bold');
                $table->addColumn('time', __m('Time'))
                    ->format(function ($values) use ($connection2, $freeLearningUnitID) {
                        $output = '';
                        $timing = null;
                        $blocks = getBlocksArray($connection2, $freeLearningUnitID);
                        if ($blocks != false) {
                            foreach ($blocks as $block) {
                                if ($block[0] == $values['freeLearningUnitID']) {
                                    if (is_numeric($block[2])) {
                                        $timing += $block[2];
                                    }
                                }
                            }
                        }
                        if (is_null($timing)) {
                            $output = __('N/A');
                        } else {
                            $minutes = intval($timing);
                            $relativeTime = __n('{count} min', '{count} mins', $minutes);
                            if ($minutes > 60) {
                                $hours = round($minutes / 60, 1);
                                $relativeTime = Format::tooltip(__n('{count} hr', '{count} '.__m('hrs'), ceil($minutes / 60), ['count' => $hours]), $relativeTime);
                            }

                            $output = !empty($timing) ? $relativeTime : Format::small(__('N/A'));
                        }

                        return $output;
                    });
                $table->addColumn('logo', '')
                    ->addClass('row-span-3 text-right')
                    ->format(function ($values) use ($gibbon) {
                        if ($values['logo'] == null) {
                            return "<img style='margin: 5px; height: 125px; width: 125px' class='user' src='".$gibbon->session->get('absoluteURL').'/themes/'.$gibbon->session->get('gibbonThemeName')."/img/anonymous_125.jpg'/><br/>";
                        } else {
                            return "<img style='margin: 5px; height: 125px; width: 125px' class='user' src='".$values['logo']."'/><br/>";
                        }
                    });
                $table->addColumn('difficulty', __m('Difficulty'));
                $table->addColumn('prerequisites', __m('Prerequisites'))
                    ->format(function ($values) use ($connection2) {
                        $output = '';
                        $prerequisitesActive = prerequisitesRemoveInactive($connection2, $values['freeLearningUnitIDPrerequisiteList']);
                        if ($prerequisitesActive != false) {
                            $prerequisites = explode(',', $prerequisitesActive);
                            $units = getUnitsArray($connection2);
                            foreach ($prerequisites as $prerequisite) {
                                $output .= $units[$prerequisite][0].'<br/>';
                            }
                        } else {
                            $output = __m('None');
                        }

                        return $output;
                    });
                $table->addColumn('departments', __('Departments'))
                    ->format(function ($values) use ($connection2, $guid) {
                        $output = '';
                        $learningAreas = getLearningAreas($connection2, $guid);
                        if ($learningAreas == '') {
                            $output = __m('No Learning Areas available.');
                        } else {
                            for ($i = 0; $i < count($learningAreas); $i = $i + 2) {
                                if (is_numeric(strpos($values['gibbonDepartmentIDList'], $learningAreas[$i]))) {
                                    $output .= __($learningAreas[($i + 1)]).'<br/>';
                                }
                            }
                        }
                        return $output;
                    });
                    $table->addColumn('authors', __m('Authors'))
                        ->format(function ($values) use ($connection2, $freeLearningUnitID) {
                            $output = '';
                            $authors = getAuthorsArray($connection2, $freeLearningUnitID);
                            foreach ($authors as $author) {
                                if ($author[3] == '') {
                                    $output .= $author[1].'<br/>';
                                } else {
                                    $output .= "<a target='_blank' href='".$author[3]."'>".$author[1].'</a><br/>';
                                }
                            }
                            return $output;
                        });

                    $table->addColumn('groupings', __m('Groupings'))
                        ->format(function ($values) use ($connection2, $freeLearningUnitID) {
                            $output = '';
                            $authors = getAuthorsArray($connection2, $freeLearningUnitID);
                            if ($values['grouping'] != '') {
                                $groupings = explode(',', $values['grouping']);
                                foreach ($groupings as $grouping) {
                                    $output .= ucwords($grouping).'<br/>';
                                }
                            }
                            return $output;
                        });

                    $table->addColumn('gibbonYearGroupIDMinimum', __m('Minimum Year Group'))
                        ->format(function ($values) use ($guid, $connection2) {
                            return getYearGroupsFromIDList($guid, $connection2, $values["gibbonYearGroupIDMinimum"]);
                        });

                echo $table->render([$values]);


                $defaultTab = 2;
                if ($canManage || (isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse_details_approval.php') && $roleCategory == 'Staff')) {
                    $defaultTab = 3;
                }
                if (isset($_GET['tab'])) {
                    $defaultTab = $_GET['tab'];
                }

                echo "<div id='tabs' style='margin: 20px 0'>";
                //Tab links
                echo '<ul>';
                echo "<li><a href='#tabs0'>".__('Unit Overview', 'Free Learning').'</a></li>';
                echo "<li><a href='#tabs1'>".__('Enrol', 'Free Learning').'</a></li>';
                if ($prerequisitesMet) {
                    if ($canManage || (isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse_details_approval.php') && $roleCategory == 'Staff')) {
                        echo "<li><a href='#tabs2'>".__('Manage Enrolment', 'Free Learning').'</a></li>';
                    }
                    echo "<li><a href='#tabs3'>".__('Content', 'Free Learning').'</a></li>';
                    echo "<li><a href='#tabs4'>".__('Resources', 'Free Learning').'</a></li>';
                    echo "<li><a href='#tabs5'>".__('Outcomes', 'Free Learning').'</a></li>';
                    echo "<li><a href='#tabs6'>".__('Exemplar Work', 'Free Learning').'</a></li>';
                }
                echo '</ul>';

                //Tabs
                echo "<div id='tabs0'>";
                echo '<h3>';
                echo __('Blurb', 'Free Learning');
                echo '</h3>';
                echo '<p>';
                echo $values['blurb'];
                echo '</p>';
                if ($values['license'] != '') {
                    echo '<h4>';
                    echo __('License', 'Free Learning');
                    echo '</h4>';
                    echo '<p>';
                    echo __('This work is shared under the following license:', 'Free Learning').' '.$values['license'];
                    echo '</p>';
                }
                if ($values['outline'] != '') {
                    echo '<h3>';
                    echo 'Outline';
                    echo '</h3>';
                    echo '<p>';
                    echo $values['outline'];
                    echo '</p>';
                }
                echo '</div>';

                if (!$prerequisitesMet) {
                    echo "<div id='tabs1'>";
                    echo Format::alert(__('You do not have access to this unit, as you have not yet met the prerequisites for it.', 'Free Learning'), 'warning');
                    echo '</div>';
                } else {

                    echo "<div id='tabs1'>";
                        //Enrolment screen spun into separate file for ease of coding
                        include './modules/Free Learning/units_browse_details_enrol.php';
                    echo '</div>';

                    if ($canManage || (isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse_details_approval.php') && $roleCategory == 'Staff')) {
                        echo "<div id='tabs2'>";
                            //Check to see if we have access to manage all enrolments, or only those belonging to ourselves
                            $manageAll = isActionAccessible($guid, $connection2, '/modules/Free Learning/enrolment_manage.php', 'Manage Enrolment_all');
                            $enrolmentType = '';
                            if ($manageAll == true) {
                                $enrolmentType = 'staffEdit';
                            } else {
                                //Check to see if we can set enrolmentType to "staffEdit" if user has rights in relevant department(s)
                                $learningAreas = getLearningAreas($connection2, $guid, true);
                                if ($learningAreas != '') {
                                    for ($i = 0; $i < count($learningAreas); $i = $i + 2) {
                                        if (is_numeric(strpos($values['gibbonDepartmentIDList'], $learningAreas[$i]))) {
                                            $enrolmentType = 'staffEdit';
                                        }
                                    }
                                }
                            }

                            echo '<p>';
                                if ($manageAll == true) {
                                    echo __m('Below you can view the students currently enrolled in this unit, including those who are working on it, those who are awaiting approval and those who have completed it.');
                                }
                                else {
                                    echo __m('Below you can view those students currently enrolled in this unit from your classes or that you mentor. This includes those who are working on it, those who are awaiting approval and those who have completed it.');
                                }
                            echo '</p>';

                            // List students whose status is Current or Complete - Pending
                            $unitGateway = $container->get(UnitGateway::class);
                            $unitStudentGateway = $container->get(UnitStudentGateway::class);

                            // Get list of my classes before we start looping, for efficiency's sake
                            $myClasses = $unitGateway->selectRelevantClassesByTeacher($gibbon->session->get('gibbonSchoolYearID'), $gibbon->session->get('gibbonPersonID'))->fetchAll(PDO::FETCH_COLUMN, 0);

                            $criteria = $unitStudentGateway->newQueryCriteria()
                                ->sortBy(['statusSort', 'collaborationKey', 'surname', 'preferredName'])
                                ->fromPOST();

                            $students = $unitStudentGateway->queryCurrentStudentsByUnit($criteria, $gibbon->session->get('gibbonSchoolYearID'), $values['freeLearningUnitID'], $gibbon->session->get('gibbonPersonID'), $manageAll);
                            $canViewStudents = isActionAccessible($guid, $connection2, '/modules/Students/student_view_details.php');
                            $customField = getSettingByScope($connection2, 'Free Learning', 'customField');

                            $collaborationKeys = [];

                            //Legend
                            $templateView = new View($container->get('twig'));
                            echo $templateView->fetchFromTemplate('unitLegend.twig.html');

                            // DATA TABLE
                            $table = DataTable::createPaginated('manageEnrolment', $criteria);

                            if ($enrolmentType == 'staffEdit') {
                                $table->addHeaderAction('addMultiple', __('Add Multiple'))
                                    ->setURL('/modules/Free Learning/units_browse_details_enrolMultiple.php')
                                    ->addParam('freeLearningUnitID', $values['freeLearningUnitID'])
                                    ->addParam('gibbonDepartmentID', $gibbonDepartmentID)
                                    ->addParam('difficulty', $difficulty)
                                    ->addParam('name', $name)
                                    ->addParam('showInactive', $showInactive)
                                    ->addParam('gibbonPersonID', $gibbonPersonID)
                                    ->addParam('view', $view)
                                    ->displayLabel();
                            }

                            $table->modifyRows(function ($student, $row) {
                                if ($student['status'] == 'Current - Pending') $row->addClass('currentPending');
                                if ($student['status'] == 'Current') $row->addClass('currentUnit');
                                if ($student['status'] == 'Evidence Not Yet Approved') $row->addClass('warning');
                                if ($student['status'] == 'Complete - Pending') $row->addClass('pending');
                                if ($student['status'] == 'Complete - Approved') $row->addClass('success');
                                if ($student['status'] == 'Exempt') $row->addClass('success');
                                return $row;
                            });

                            $unitStudentGateway = $container->get(UnitStudentGateway::class);
                            $table->addExpandableColumn('commentStudent')
                                ->format(function ($student) use (&$page, &$unitStudentGateway) {
                                    if ($student['status'] == 'Current' || $student['status'] == 'Current - Pending') return;

                                    $logs = $unitStudentGateway->selectUnitStudentDiscussion($student['freeLearningUnitStudentID'])->fetchAll();

                                    $logs = array_map(function ($item) {
                                        $item['comment'] = Format::hyperlinkAll($item['comment']);
                                        return $item;
                                    }, $logs);

                                    return $page->fetchFromTemplate('ui/discussion.twig.html', [
                                        'discussion' => $logs
                                    ]);
                                });

                            $table->addColumn('student', __('Student'))
                                ->sortable(['surname', 'preferredName'])
                                ->width('35%')
                                ->format(function ($student) use ($canViewStudents, $customField) {
                                    $output = '';
                                    $url = './index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$student['gibbonPersonID'];
                                    $name = Format::name('', $student['preferredName'], $student['surname'], 'Student', true, true);

                                    $output = $canViewStudents
                                        ? Format::link($url, $name)
                                        : $name;

                                    if (!$canViewStudents) {
                                        $output .= '<br/>'.Format::link('mailto:'.$student['email'], $student['email']);
                                    }
                                    $fields = json_decode($student['fields'], true);
                                    if (!empty($fields[$customField])) {
                                        $output .= '<br/>'.Format::small($fields[$customField]);
                                    }
                                    return $output;
                                });

                            $table->addColumn('status', __('Status'))
                                ->description(__m('Enrolment Method'))
                                ->sortable('statusSort')
                                ->width('25%')
                                ->format(function ($student) {
                                    $enrolmentMethod = ucfirst(preg_replace('/(\w+)([A-Z])/U', '\\1 \\2', $student['enrolmentMethod']));
                                    return $student['status'] . '<br/>' . Format::small($enrolmentMethod);
                                });

                            $table->addColumn('classMentor', __m('Class/Mentor'))
                                ->description(__m('Grouping'))
                                ->sortable(['course', 'class'])
                                ->width('20%')
                                ->format(function ($student) use (&$collaborationKeys) {
                                    $return = '';
                                    if ($student['enrolmentMethod'] == 'class') {
                                        if (!empty($student['course']) && !empty($student['class'])) {
                                            $return .= Format::courseClassName($student['course'], $student['class']);
                                        } else {
                                            $return .= Format::small(__('N/A'));
                                        }
                                    } else if ($student['enrolmentMethod'] == 'schoolMentor') {
                                        $return .= formatName('', $student['mentorpreferredName'], $student['mentorsurname'], 'Student', false);
                                    } else if ($student['enrolmentMethod'] == 'externalMentor') {
                                        $return .= $student['nameExternalMentor'];
                                    }

                                    $grouping = $student['grouping'];
                                    if ($student['collaborationKey'] != '') {
                                        // Get the index for the group, otherwise add it to the array
                                        $group = array_search($student['collaborationKey'], $collaborationKeys);
                                        if ($group === false) {
                                            $collaborationKeys[] = $student['collaborationKey'];
                                            $group = count($collaborationKeys);
                                        } else {
                                            $group++;
                                        }
                                        $grouping .= " (".__m("Group")." ".$group.")";
                                    }
                                    $return .= '<br/>' . Format::small($grouping);

                                    return $return;
                                });

                            $table->addColumn('view', __('View'))
                                ->notSortable()
                                ->width('10%')
                                ->format(function ($student) {
                                    if (empty($student['evidenceLocation'])) return;

                                    $url = $student['evidenceType'] == 'Link'
                                        ? $student['evidenceLocation']
                                        : './'.$student['evidenceLocation'];

                                    return Format::link($url, __('View'), ['target' => '_blank']);
                                });

                            // ACTIONS
                            $table->addActionColumn()
                                ->addParam('freeLearningUnitStudentID')
                                ->addParam('freeLearningUnitID')
                                ->addParam('gibbonDepartmentID', $gibbonDepartmentID)
                                ->addParam('difficulty', $difficulty)
                                ->addParam('name', $name)
                                ->addParam('showInactive', $showInactive)
                                ->addParam('gibbonPersonID', $gibbonPersonID)
                                ->addParam('view', $view)
                                ->addParam('sidebar', 'true')
                                ->format(function ($student, $actions) use ($manageAll, $enrolmentType, $myClasses, $gibbon) {
                                    // Check to see if we can edit this class's enrolment (e.g. we have $manageAll or this is one of our classes or we are the mentor)
                                    $editEnrolment = $manageAll ? true : false;
                                    if ($student['enrolmentMethod'] == 'class') {
                                        // Is teacher of this class?
                                        if (in_array($student['gibbonCourseClassID'], $myClasses)) {
                                            $editEnrolment = true;
                                        }
                                    } elseif ($student['enrolmentMethod'] == 'schoolMentor' && $student['gibbonPersonIDSchoolMentor'] == $gibbon->session->get('gibbonPersonID')) {
                                        // Is mentor of this student?
                                        $editEnrolment = true;
                                    }

                                    if ($enrolmentType == 'staffEdit' || $editEnrolment) {
                                        if ($editEnrolment && ($student['status'] == 'Current' || $student['status'] == 'Complete - Pending' or $student['status'] == 'Complete - Approved' or $student['status'] == 'Evidence Not Yet Approved')) {
                                            $actions->addAction('edit', __('Edit'))
                                                ->setURL('/modules/Free Learning/units_browse_details_approval.php');
                                        }
                                        if ($editEnrolment) {
                                            $actions->addAction('delete', __('Delete'))
                                                ->setURL('/modules/Free Learning/units_browse_details_delete.php');
                                        }
                                    }

                                    if ($editEnrolment && $student['status'] == 'Current - Pending' && $student['enrolmentMethod'] == 'schoolMentor') {
                                        $actions->addAction('approve', __m('Approve'))
                                                ->setIcon('iconTick')
                                                ->addParam('confirmationKey', $student['confirmationKey'])
                                                ->addParam('response', 'Y')
                                                ->setURL('/modules/Free Learning/units_mentorProcess.php')
                                                ->directLink();

                                        $actions->addAction('reject', __m('Reject'))
                                                ->setIcon('iconCross')
                                                ->addParam('confirmationKey', $student['confirmationKey'])
                                                ->addParam('response', 'N')
                                                ->setURL('/modules/Free Learning/units_mentorProcess.php')
                                                ->directLink();
                                    }
                                });

                            echo $table->render($students);

                        echo "</div>";
                    }
                    echo '<div id="tabs3" style="border-width: 1px 0px 0px 0px !important; background-color: transparent !important; padding-left: 0; padding-right: 0; overflow: initial;">';

                    $dataBlocks = ['freeLearningUnitID' => $freeLearningUnitID];
                    $sqlBlocks = 'SELECT * FROM freeLearningUnitBlock WHERE freeLearningUnitID=:freeLearningUnitID ORDER BY sequenceNumber';

                    $blocks = $pdo->select($sqlBlocks, $dataBlocks)->fetchAll();

                    if (empty($blocks)) {
                        echo Format::alert(__('There are no records to display.'));
                    } else {
                        $templateView = $container->get(View::class);
                        $resourceContents = '';

                        $blockCount = 0;
                        foreach ($blocks as $block) {
                            echo $templateView->fetchFromTemplate('unitBlock.twig.html', $block + [
                                'roleCategory' => $roleCategory,
                                'gibbonPersonID' => $gibbon->session->get('username') ?? '',
                                'blockCount' => $blockCount
                            ]);
                            $resourceContents .= $block['contents'];
                            $blockCount++;
                        }

                        // Enable p5js widgets in smart blocks
                        if (stripos($resourceContents, '<script type="text/p5"') !== false) {
                            echo '<script src="//toolness.github.io/p5.js-widget/p5-widget.js"></script>';
                        }
                    }

                    echo '</div>';
                    echo "<div id='tabs4'>";
                    //Resources
                    $noReosurces = true;

                    //Links
                    $links = '';
                    $linksArray = array();
                    $linksCount = 0;
                    $dom = new DOMDocument();
                    @$dom->loadHTML($resourceContents);
                    foreach ($dom->getElementsByTagName('a') as $node) {
                        if ($node->nodeValue != '') {
                            $linksArray[$linksCount] = "<li><a href='".$node->getAttribute('href')."'>".$node->nodeValue.'</a></li>';
                            ++$linksCount;
                        }
                    }

                    $linksArray = array_unique($linksArray);
                    natcasesort($linksArray);

                    foreach ($linksArray as $link) {
                        $links .= $link;
                    }

                    if ($links != '') {
                        echo '<h2>';
                        echo 'Links';
                        echo '</h2>';
                        echo '<ul>';
                        echo $links;
                        echo '</ul>';
                        $noReosurces = false;
                    }

                    //Images
                    $images = '';
                    $imagesArray = array();
                    $imagesCount = 0;
                    $dom2 = new DOMDocument();
                    @$dom2->loadHTML($resourceContents);
                    foreach ($dom2->getElementsByTagName('img') as $node) {
                        if ($node->getAttribute('src') != '') {
                            $imagesArray[$imagesCount] = "<img class='resource' style='margin: 10px 0; max-width: 560px' src='".$node->getAttribute('src')."'/><br/>";
                            ++$imagesCount;
                        }
                    }

                    $imagesArray = array_unique($imagesArray);
                    natcasesort($imagesArray);

                    foreach ($imagesArray as $image) {
                        $images .= $image;
                    }

                    if ($images != '') {
                        echo '<h2>';
                        echo 'Images';
                        echo '</h2>';
                        echo $images;
                        $noReosurces = false;
                    }

                    //Embeds
                    $embeds = '';
                    $embedsArray = array();
                    $embedsCount = 0;
                    $dom2 = new DOMDocument();
                    @$dom2->loadHTML($resourceContents);
                    foreach ($dom2->getElementsByTagName('iframe') as $node) {
                        if ($node->getAttribute('src') != '') {
                            $embedsArray[$embedsCount] = "<iframe style='max-width: 560px' width='".$node->getAttribute('width')."' height='".$node->getAttribute('height')."' src='".$node->getAttribute('src')."' frameborder='".$node->getAttribute('frameborder')."'></iframe>";
                            ++$embedsCount;
                        }
                    }

                    $embedsArray = array_unique($embedsArray);
                    natcasesort($embedsArray);

                    foreach ($embedsArray as $embed) {
                        $embeds .= $embed.'<br/><br/>';
                    }

                    if ($embeds != '') {
                        echo '<h2>';
                        echo 'Embeds';
                        echo '</h2>';
                        echo $embeds;
                        $noReosurces = false;
                    }

                    //No resources!
                    if ($noReosurces) {
                        echo "<div class='error'>";
                        echo __('There are no records to display.');
                        echo '</div>';
                    }
                    echo '</div>';
                    echo "<div id='tabs5'>";
                        //Spit out outcomes
                        $unitOutcomeGateway = $container->get(UnitOutcomeGateway::class);

                        $criteria = $unitOutcomeGateway->newQueryCriteria(true)
                            ->fromPOST();

                        $outcomes = $unitOutcomeGateway->selectOutcomesByUnit($freeLearningUnitID)->fetchAll();

                        $table = DataTable::createPaginated('outcomes', $criteria);

                        $table->addExpandableColumn('content');
                        $table->addColumn('scope', __('scope'));
                        $table->addColumn('category', __('Category'));
                        $table->addColumn('name', __('Name'))
                            ->format(function($outcome) {
                                $output = $outcome['nameShort']."<br/>";
                                $output .= "<div class=\"text-xxs\">".$outcome['name']."</div>";
                                return $output;
                            });
                        $table->addColumn('yearGroups', __('Year Groups'))
                            ->format(function($outcome) use ($guid, $connection2) {
                                return getYearGroupsFromIDList($guid, $connection2, $outcome['gibbonYearGroupIDList']);
                            });

                        echo $table->render($outcomes);
                    echo '</div>';
                    echo "<div id='tabs6'>";
                        //Spit out exemplar work
                        try {
                            $dataWork = array('freeLearningUnitID' => $freeLearningUnitID);
                            $sqlWork = "SELECT freeLearningUnitStudent.*, preferredName FROM freeLearningUnitStudent JOIN gibbonPerson ON (freeLearningUnitStudent.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID) WHERE freeLearningUnitID=:freeLearningUnitID AND exemplarWork='Y' ORDER BY timestampCompleteApproved DESC";
                            $resultWork = $connection2->prepare($sqlWork);
                            $resultWork->execute($dataWork);
                        } catch (PDOException $e) {
                            echo "<div class='error'>".$e->getMessage().'</div>';
                        }
                    if ($resultWork->rowCount() < 1) {
                        echo "<div class='error'>";
                        echo __('There are no records to display.');
                        echo '</div>';
                    } else {
                        while ($rowWork = $resultWork->fetch()) {
                            $students = '';
                            if ($rowWork['grouping'] == 'Individual') { //Created by a single student
                            $students = $rowWork['preferredName'];
                            } else { //Created by a group of students
                                        try {
                                            $dataStudents = array('collaborationKey' => $rowWork['collaborationKey']);
                                            $sqlStudents = "SELECT preferredName FROM freeLearningUnitStudent JOIN gibbonPerson ON (freeLearningUnitStudent.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID) JOIN freeLearningUnit ON (freeLearningUnitStudent.freeLearningUnitID=freeLearningUnit.freeLearningUnitID) WHERE active='Y' AND collaborationKey=:collaborationKey ORDER BY preferredName";
                                            $resultStudents = $connection2->prepare($sqlStudents);
                                            $resultStudents->execute($dataStudents);
                                        } catch (PDOException $e) {
                                        }
                                while ($rowStudents = $resultStudents->fetch()) {
                                    $students .= $rowStudents['preferredName'].', ';
                                }
                                if ($students != '') {
                                    $students = substr($students, 0, -2);
                                    $students = preg_replace('/,([^,]*)$/', ' & \1', $students);
                                }
                            }

                            echo '<h3>';
                            echo $students." . <span style='font-size: 75%'>".__('Shared on', 'Free Learning').' '.dateConvertBack($guid, $rowWork['timestampCompleteApproved']).'</span>';
                            echo '</h3>';
                            //DISPLAY WORK.
                            echo '<h4 style=\'margin-top: 0px\'>'.__('Student Work', 'Free Learning').'</h4>';
                            if ($rowWork['exemplarWorkEmbed'] =='') { //It's not an embed
                                $extension = strrchr($rowWork['evidenceLocation'], '.');
                                if (strcasecmp($extension, '.gif') == 0 or strcasecmp($extension, '.jpg') == 0 or strcasecmp($extension, '.jpeg') == 0 or strcasecmp($extension, '.png') == 0) { //Its an image
                                    echo "<p>";
                                    if ($rowWork['evidenceType'] == 'File') { //It's a file
                                        echo "<a target='_blank' href='".$gibbon->session->get('absoluteURL').'/'.$rowWork['evidenceLocation']."'><img class='user' style='max-width: 550px' src='".$gibbon->session->get('absoluteURL').'/'.$rowWork['evidenceLocation']."'/></a>";
                                    } else { //It's a link
                                        echo "<a target='_blank' href='".$gibbon->session->get('absoluteURL').'/'.$rowWork['evidenceLocation']."'><img class='user' style='max-width: 550px' src='".$rowWork['evidenceLocation']."'/></a>";
                                    }
                                    echo '</p>';
                                } else { //Not an image
                                    echo '<p class=\'button\'>';
                                    if ($rowWork['evidenceType'] == 'File') { //It's a file
                                        echo "<a class='button'target='_blank' href='".$gibbon->session->get('absoluteURL').'/'.$rowWork['evidenceLocation']."'>".__('Click to View Work', 'Free Learning').'</a>';
                                    } else { //It's a link
                                        echo "<a class='button' target='_blank' href='".$rowWork['evidenceLocation']."'>".__('Click to View Work', 'Free Learning').'</a>';
                                    }
                                    echo '</p>';
                                }
                            } else {
                                echo '<p>';
                                print $rowWork['exemplarWorkEmbed'] ;
                                echo '</p>';
                            }
                            //DISPLAY STUDENT COMMENT
                            if ($rowWork['commentStudent'] != '') {
                                echo '<h4>'.__('Student Comment', 'Free Learning').'</h4>';
                                echo '<p style=\'margin-bottom: 0px\'>';
                                echo nl2br($rowWork['commentStudent']);
                                echo '</p>';
                            }
                            //DISPLAY TEACHER COMMENT
                            if ($rowWork['commentApproval'] != '') {
                                if ($rowWork['commentStudent'] != '') {
                                    echo '<br/>';
                                }
                                echo '<h4>'.__('Teacher Comment', 'Free Learning').'</h4>';
                                echo '<p>';
                                echo $rowWork['commentApproval'];
                                echo '</p>';
                            }
                        }
                    }
                    echo '</div>';

                }
                echo '</div>';

                echo "<script type='text/javascript'>
                    $( \"#tabs\" ).tabs({
                            active: $defaultTab,
                            ajaxOptions: {
                                error: function( xhr, status, index, anchor ) {
                                    $( anchor.hash ).html(
                                        \"Couldn't load this tab.\" );
                                }
                            }
                        });
                </script>";

            }
        }
    }
}
?>
