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

use Gibbon\Module\FreeLearning\Domain\MentorGroupGateway;
use Gibbon\Module\FreeLearning\Domain\MentorGroupPersonGateway;

require_once '../../gibbon.php';

$freeLearningMentorGroupID = $_POST['freeLearningMentorGroupID'] ?? '';

$URL = $gibbon->session->get('absoluteURL').'/index.php?q=/modules/Free Learning/mentorGroups_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Free Learning/mentorGroups_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $mentorGroupGateway = $container->get(MentorGroupGateway::class);
    $mentorGroupPersonGateway = $container->get(MentorGroupPersonGateway::class);

    if (empty($freeLearningMentorGroupID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if (!$mentorGroupGateway->exists($freeLearningMentorGroupID)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $mentorGroupGateway->delete($freeLearningMentorGroupID);
    $deleted &= $mentorGroupPersonGateway->deleteWhere(['freeLearningMentorGroupID' => $freeLearningMentorGroupID]);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
