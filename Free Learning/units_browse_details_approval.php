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
along with this program. If not, see <http:// www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\FreeLearning\Domain\UnitGateway;
use Gibbon\Module\FreeLearning\Domain\UnitStudentGateway;

//  Module includes
require_once __DIR__ . '/moduleFunctions.php';

$publicUnits = getSettingByScope($connection2, 'Free Learning', 'publicUnits');

if (isActionAccessible($guid, $connection2, '/modules/Free Learning/units_browse_details_approval.php') == false) {
    //  Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //  Get action with highest precedence
    $highestAction = getHighestGroupedAction($guid, '/modules/Free Learning/units_browse_details_approval.php', $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;
    }

    $roleCategory = getRoleCategory($gibbon->session->get('gibbonRoleIDCurrent'), $connection2);
    $canManage = isActionAccessible($guid, $connection2, '/modules/Free Learning/units_manage.php') && $highestAction == 'Browse Units_all';

    //  Get params
    $freeLearningUnitStudentID = $_GET['freeLearningUnitStudentID'] ?? '';
    $freeLearningUnitID = $_GET['freeLearningUnitID'] ?? '';
    $gibbonPersonID = $canManage && !empty($_GET['gibbonPersonID'])
        ? $_GET['gibbonPersonID']
        : $gibbon->session->get('gibbonPersonID');

    $urlParams = [
        'freeLearningUnitStudentID' => $freeLearningUnitStudentID,
        'freeLearningUnitID'        => $freeLearningUnitID,
        'showInactive'              => $_GET['showInactive'] ?? 'N',
        'gibbonDepartmentID'        => $_GET['gibbonDepartmentID'] ?? '',
        'difficulty'                => $_GET['difficulty'] ?? '',
        'name'                      => $_GET['name'] ?? '',
        'view'                      => $_GET['view'] ?? '',
        'sidebar'                   => 'true',
        'gibbonPersonID'            => $gibbonPersonID,
        'tab'                       => 2,
    ];

    $page->breadcrumbs
        ->add(__m('Browse Units'), 'units_browse.php', $urlParams)
        ->add(__m('Unit Details'), 'units_browse_details.php', $urlParams)
        ->add(__m('Approval'));

    $unitGateway = $container->get(UnitGateway::class);
    $unitStudentGateway = $container->get(UnitStudentGateway::class);

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    //  Check that the required values are present
    if (empty($freeLearningUnitID) || empty($freeLearningUnitStudentID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    //  Check that the record exists
    $values = $unitStudentGateway->getUnitStudentDetailsByID($freeLearningUnitID, null, $freeLearningUnitStudentID);
    if (empty($values)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $values['authors'] = $unitGateway->selectUnitAuthorsByID($freeLearningUnitID)->fetchAll();
    $values['departments'] = $unitGateway->selectUnitDepartmentsByID($freeLearningUnitID)->fetchAll(PDO::FETCH_COLUMN, 0);

    $proceed = false;
    // Check to see if we have access to manage all enrolments, or only those belonging to ourselves
    $manageAll = isActionAccessible($guid, $connection2, '/modules/Free Learning/enrolment_manage.php', 'Manage Enrolment_all');
    if ($manageAll == true) {
        $proceed = true;
    }
    else if ($values['enrolmentMethod'] == 'schoolMentor' && $values['gibbonPersonIDSchoolMentor'] == $gibbon->session->get('gibbonPersonID')) {
        $proceed = true;
    } else {
        $learningAreas = getLearningAreas($connection2, $guid, true);
        if ($learningAreas != '') {
            for ($i = 0; $i < count($learningAreas); $i = $i + 2) {
                if (is_numeric(strpos($values['gibbonDepartmentIDList'], $learningAreas[$i]))) {
                    $proceed = true;
                }
            }
        }
    }

    // Check to see if class is in one teacher teachers
    if ($values['enrolmentMethod'] == 'class') { // Is teacher of this class?
        try {
            $dataClasses = array('gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID'), 'gibbonPersonID' => $gibbon->session->get('gibbonPersonID'), 'gibbonCourseClassID' => $values['gibbonCourseClassID']);
            $sqlClasses = "SELECT gibbonCourseClass.gibbonCourseClassID FROM gibbonCourse JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID) JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) WHERE gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID AND (role='Teacher' OR role='Assistant')";
            $resultClasses = $connection2->prepare($sqlClasses);
            $resultClasses->execute($dataClasses);
        } catch (PDOException $e) {}
        if ($resultClasses->rowCount() > 0) {
            $proceed = true;
        }
    }

    if ($proceed == false) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    //  DETAILS TABLE
    $table = DataTable::createDetails('personal');
    $table->addHeaderAction('edit', __('Edit'))
        ->setURL('/modules/Free Learning/units_manage_edit.php')
        ->addParams($urlParams)
        ->displayLabel();

    $table->addColumn('name', __('Unit Name'));
    $table->addColumn('departments', __('Department'))->format(function ($unit) {
        if (!empty($unit['departments'])) {
            return implode('<br/>', $unit['departments']);
        } else {
            return __m('No Learning Areas available.');
        }
    });
    $table->addColumn('authors', __('Authors'))->format(Format::using('nameList', 'authors'));

    echo $table->render([$values]);

    $alert = '';
    $collaborativeAssessment = getSettingByScope($connection2, 'Free Learning', 'collaborativeAssessment');
    if ($collaborativeAssessment == 'Y' && !empty($values['collaborationKey'])) {
        $alert = Format::alert(__m('Collaborative Assessment is enabled: you will be giving feedback to all members of this group in one go.'), 'message');
    }

    $gibbonHookID = $pdo->selectOne("SELECT gibbonHookID FROM gibbonHook
        JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonHook.gibbonModuleID)
        WHERE gibbonModule.name='Free Learning' AND gibbonHook.type='Student Profile'");

    //  COMMENT FORM
    $form = Form::create('enrolComment', $gibbon->session->get('absoluteURL').'/modules/Free Learning/units_browse_details_commentProcess.php?'.http_build_query($urlParams));
    $form->setClass('blank');

    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->addHiddenValue('freeLearningUnitID', $freeLearningUnitID);
    $form->addHiddenValue('freeLearningUnitStudentID', $freeLearningUnitStudentID);

    //  DISCUSSION
    $logs = $unitStudentGateway->selectUnitStudentDiscussion($freeLearningUnitStudentID)->fetchAll();

    $logs = array_map(function ($item) {
        $item['comment'] = Format::hyperlinkAll($item['comment']);
        return $item;
    }, $logs);

    $logs = array_map(function ($item) use ($gibbonHookID) {
        $item['url'] = !empty($item['gibbonPersonID']) && $item['category'] == 'Student'
            ? './index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$item['gibbonPersonID'].'&hook=Free Learning&module=Free Learning&action=Unit History By Student_all&gibbonHookID='.$gibbonHookID
            : '';
        return $item;
    }, $logs);

    $form->addRow()->addContent($page->fetchFromTemplate('ui/discussion.twig.html', [
        'title' => __('Comments'),
        'discussion' => $logs
    ]));

    //  ADD COMMENT
    $commentBox = $form->getFactory()->createColumn()->addClass('flex flex-col');
    $commentBox->addTextArea('addComment')
        ->placeholder(__m('Leave a comment'))
        ->setClass('flex w-full')
        ->setRows(3);
    $commentBox->addButton(__m('Add Comment'))
        ->onClick('$(this).prop("disabled", true).wrap("<span class=\"submitted\"></span>");document.getElementById("enrolComment").submit()')
        ->setClass('button rounded-sm right');

    $form->addRow()->addClass('-mt-4')->addContent($page->fetchFromTemplate('ui/discussion.twig.html', [
        'discussion' => [[
            'surname' => $gibbon->session->get('surname'),
            'preferredName' => $gibbon->session->get('preferredName'),
            'image_240' => $gibbon->session->get('image_240'),
            'comment' => $commentBox->getOutput(),
        ]]
    ]));

    echo $form->getOutput();

    //  Not ready for approval
    if ($values['status'] == 'Current' || $values['status'] == 'Evidence Not Yet Approved') {
        return;
    }

    //  FORM
    $form = Form::create('approval', $gibbon->session->get('absoluteURL').'/modules/Free Learning/units_browse_details_approvalProcess.php?'.http_build_query($urlParams));
    $form->setTitle(__m('Unit Complete Approval'));
    $form->setDescription($alert.'<p>'.__m('Use the table below to indicate student completion, based on the evidence shown on the previous page. Leave the student a comment in way of feedback.').'</p>');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->addHiddenValue('freeLearningUnitID', $freeLearningUnitID);
    $form->addHiddenValue('freeLearningUnitStudentID', $freeLearningUnitStudentID);

    if ($collaborativeAssessment == 'Y' && !empty($values['collaborationKey'])) {
        $row = $form->addRow();
            $row->addLabel('student', __('Students'));
            $col = $row->addColumn()->setClass('flex-col');

        $collaborators = $unitStudentGateway->selectUnitCollaboratorsByKey($values['collaborationKey'])->fetchAll();
        foreach ($collaborators as $index => $collaborator) {
            $in = ($collaborator['inCount'] > 0 && isActionAccessible($guid, $connection2, "/modules/Individual Needs/in_view.php")) ? " (".__('Individual Needs').")": "" ;
            $col->addTextField('student'.$index)->readonly()->setValue(Format::name('', $collaborator['preferredName'], $collaborator['surname'], 'Student', false).$in);
        }
    } else {
        $in = ($values['inCount'] > 0 && isActionAccessible($guid, $connection2, "/modules/Individual Needs/in_view.php")) ? " (".__('Individual Needs').")": "" ;
        $row = $form->addRow();
            $row->addLabel('student', __('Student'));
            $row->addTextField('student')->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', false).$in);
    }

    $submissionLink = $values['evidenceType'] == 'Link'
        ? $values['evidenceLocation']
        : $gibbon->session->get('absoluteURL').'/'.$values['evidenceLocation'];

    $row = $form->addRow();
        $row->addLabel('submission', __m('Submission'));
        $row->addContent(Format::link($submissionLink, __m('View Submission'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

    $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('commentApproval', __m('Teacher Comment'))->description(__m('Leave a comment on the student\'s progress.'));
        $col->addEditor('commentApproval', $guid)->setRows(15)->showMedia()->required();

    $statuses = [
        'Complete - Approved' => __m('Complete - Approved'),
        'Evidence Not Yet Approved' => __m('Evidence Not Yet Approved'),
    ];

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray($statuses)->required()->placeholder()->selected($values['status']);

    $form->toggleVisibilityByClass('approved')->onSelect('status')->when('Complete - Approved');

    $row = $form->addRow()->addClass('approved');
        $row->addLabel('exemplarWork', __m('Exemplar Work'))->description(__m('Work and comments will be made viewable to other users.'));
        $row->addYesNo('exemplarWork')->required()->selected($values['exemplarWork'] ?? 'N');

    $form->toggleVisibilityByClass('exemplarYes')->onSelect('exemplarWork')->when('Y');

    $row = $form->addRow()->addClass('exemplarYes');
        $row->addLabel('exemplarWorkThumb', __m('Exemplar Work Thumbnail Image'))->description(__('150x150px jpg/png/gif'));
        $row->addFileUpload('file')
            ->accepts('.jpg,.jpeg,.gif,.png')
            ->setAttachment('exemplarWorkThumb', $gibbon->session->get('absoluteURL'), $values['exemplarWorkThumb']);

    $row = $form->addRow()->addClass('exemplarYes');
        $row->addLabel('exemplarWorkLicense', __m('Exemplar Work Thumbnail Image Credit'))->description(__m('Credit and license for image used above.'));
        $row->addTextField('exemplarWorkLicense')->maxLength(255)->setValue($values['exemplarWorkLicense']);

    $row = $form->addRow()->addClass('exemplarYes');
        $row->addLabel('exemplarWorkEmbed', __m('Exemplar Work Embed'))->description(__m('Include embed code, otherwise link to work will be used.'));
        $row->addTextField('exemplarWorkEmbed')->maxLength(255)->setValue($values['exemplarWorkEmbed']);

    $enableManualBadges = getSettingByScope($connection2, 'Free Learning', 'enableManualBadges');
    if ($enableManualBadges == 'Y' && isModuleAccessible($guid, $connection2, '/modules/Badges/badges_grant.php')) {
        $data = [];
        $sql = "SELECT badgesBadgeID as value, name FROM badgesBadge WHERE active='Y' ORDER BY name";
        $row = $form->addRow()->addClass('approved');
            $row->addLabel('badgesBadgeID', __m('Badge'))->description(__m('Manually grant a badge'));
            $row->addSelect('badgesBadgeID')->fromQuery($pdo, $sql, $data)->placeholder();
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
