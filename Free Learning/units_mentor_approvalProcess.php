<?php

use Gibbon\Domain\System\DiscussionGateway;
use Gibbon\Module\FreeLearning\Domain\UnitStudentGateway;
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

require_once '../../gibbon.php';

require_once  './moduleFunctions.php';

//Get parameters
$freeLearningUnitStudentID = $_POST['freeLearningUnitStudentID'] ?? null;
$freeLearningUnitID = $_POST['freeLearningUnitID'] ?? null;
$confirmationKey = $_POST['confirmationKey'] ?? null;

//Set return URL
$URL = $gibbon->session->get('absoluteURL').'/index.php?q=/modules/Free Learning/units_mentor_approval.php&sidebar=true&freeLearningUnitStudentID=$freeLearningUnitStudentID&confirmationKey=$confirmationKey';

if ($freeLearningUnitStudentID == '' or $freeLearningUnitID == '' or $confirmationKey == '') {
    $URL .= '&return=error3';
    header("Location: {$URL}");
} else {
    //Check student & confirmation key
    try {
        $data = array('freeLearningUnitStudentID' => $freeLearningUnitStudentID, 'freeLearningUnitID' => $freeLearningUnitID, 'confirmationKey' => $confirmationKey) ;
        $sql = 'SELECT freeLearningUnitStudent.*, freeLearningUnit.name AS unit FROM freeLearningUnitStudent JOIN freeLearningUnit ON (freeLearningUnitStudent.freeLearningUnitID=freeLearningUnit.freeLearningUnitID) WHERE freeLearningUnitStudentID=:freeLearningUnitStudentID AND freeLearningUnit.freeLearningUnitID=:freeLearningUnitID AND confirmationKey=:confirmationKey AND status=\'Complete - Pending\'';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }

    if ($result->rowCount()!=1) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }
    else {
        $values = $result->fetch() ;
        $name = $values['unit'];

        //Get Inputs
        $status = $_POST['status'] ?? '';
        $gibbonPersonIDStudent = $values['gibbonPersonIDStudent'] ?? '';
        $commentApproval = $_POST['commentApproval'] ?? '';
        $commentApproval = trim(preg_replace('/^<p>|<\/p>$/i', '', $commentApproval));
        $badgesBadgeID = $_POST['badgesBadgeID'] ?? '';

        //Validation
        if ($commentApproval == '') {
            //Fail 3
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } elseif ($status != 'Complete - Approved' && $status != 'Evidence Not Yet Approved') {
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            // Post Discussion
            $collaborativeAssessment = getSettingByScope($connection2, 'Free Learning', 'collaborativeAssessment');

            if ($values['enrolmentMethod'] == 'schoolMentor') {
                $discussionGateway = $container->get(DiscussionGateway::class);
                $unitStudentGateway = $container->get(UnitStudentGateway::class);

                $data = [
                    'foreignTable'         => 'freeLearningUnitStudent',
                    'foreignTableID'       => $freeLearningUnitStudentID,
                    'gibbonModuleID'       => getModuleIDFromName($connection2, 'Free Learning'),
                    'gibbonPersonID'       => $gibbon->session->get('gibbonPersonID'),
                    'gibbonPersonIDTarget' => $values['gibbonPersonIDStudent'],
                    'comment'              => $commentApproval,
                    'type'                 => $status,
                    'tag'                  => $status == 'Complete - Approved' ? 'success' : 'warning',
                ];

                if ($collaborativeAssessment == 'Y' AND !empty($values['collaborationKey'])) {
                    $collaborators = $unitStudentGateway->selectBy(['collaborationKey' => $values['collaborationKey']])->fetchAll();
                    foreach ($collaborators as $collaborator) {
                        $data['foreignTableID'] = $collaborator['freeLearningUnitStudentID'];
                        $data['gibbonPersonIDTarget'] = $collaborator['gibbonPersonIDStudent'];
                        $discussionGateway->insert($data);
                    }
                } else {
                    $collaborators = [$values];
                    $discussionGateway->insert($data);
                }
            }

            // Write to database
            $unitStudentGateway = $container->get(UnitStudentGateway::class);

            $data = [
                'status' => $status,
                'commentApproval' => $commentApproval,
                'gibbonPersonIDApproval' => $gibbon->session->get('gibbonPersonID') ?? null,
                'timestampCompleteApproved' => date('Y-m-d H:i:s')
            ];

            if ($collaborativeAssessment == 'Y' AND !empty($values['collaborationKey'])) {
                $updated = $unitStudentGateway->updateWhere(['collaborationKey' => $values['collaborationKey']], $data);
            } else {
                $updated = $unitStudentGateway->update($freeLearningUnitStudentID, $data);
            }

            if ($status == 'Complete - Approved') { //APPROVED!
                // Attempt to notify the student(s) and grant awards
                foreach ($collaborators as $collaborator) {
                    $text = sprintf(__($guid, 'Your mentor has approved your request for unit completion (%1$s).', 'Free Learning'), $name);
                    $actionLink = "/index.php?q=/modules/Free Learning/units_browse_details.php&freeLearningUnitID=$freeLearningUnitID&gibbonDepartmentID=&difficulty=&name=&showInactive=&sidebar=true&tab=1";
                    setNotification($connection2, $guid, $collaborator['gibbonPersonIDStudent'], $text, 'Free Learning', $actionLink);
                    grantBadges($connection2, $guid, $collaborator['gibbonPersonIDStudent']);
                }

                // Deal with manually granted badges
                $enableManualBadges = getSettingByScope($connection2, 'Free Learning', 'enableManualBadges');
                if ($enableManualBadges == 'Y' && isModuleAccessible($guid, $connection2, '/modules/Badges/badges_grant.php') && !is_null($badgesBadgeID)) {
                    foreach ($collaborators as $collaborator) {
                        $data = array('badgesBadgeID' => $badgesBadgeID, 'gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID'), 'date' => date('Y-m-d'), 'gibbonPersonID' => $collaborator['gibbonPersonIDStudent'], 'comment' => '', 'gibbonPersonIDCreator' => $gibbon->session->get('gibbonPersonID',''));
                        $sql = 'INSERT INTO badgesBadgeStudent SET badgesBadgeID=:badgesBadgeID, gibbonSchoolYearID=:gibbonSchoolYearID, date=:date, gibbonPersonID=:gibbonPersonID, comment=:comment, gibbonPersonIDCreator=:gibbonPersonIDCreator';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    }
                }

                $URL .= '&return=success0';
                header("Location: {$URL}");
            } elseif ($status == 'Evidence Not Yet Approved') { //NOT YET APPROVED
                // Attempt to notify the student(s)
                foreach ($collaborators as $collaborator) {
                    $text = sprintf(__($guid, 'Your mentor has responded to your request for unit completion, but your evidence has not been approved (%1$s).', 'Free Learning'), $name);
                    $actionLink = "/index.php?q=/modules/Free Learning/units_browse_details.php&freeLearningUnitID=$freeLearningUnitID&gibbonDepartmentID=$gibbonDepartmentID&difficulty=$difficulty&name=$name&showInactive=$showInactive&sidebar=true&tab=1&view=$view";
                    setNotification($connection2, $guid, $collaborator['gibbonPersonIDStudent'], $text, 'Free Learning', $actionLink);
                }

                $URL .= '&return=success1';
                header("Location: {$URL}");
            }
        }
    }
}
