<?php

/*
**************************************************************************************************************************
** CORAL Resources Module v. 1.0
**
** Copyright (c) 2010 University of Notre Dame
**
** This file is part of CORAL.
**
** CORAL is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
**
** CORAL is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License along with CORAL.  If not, see <http://www.gnu.org/licenses/>.
**
**************************************************************************************************************************
*/

session_start();

include_once '../directory.php';
include_once '../user.php';

$config = new Configuration();
$util = new Utility();


$config = new Configuration();
$resourceID = $_GET['resourceID'];
$resource = new Resource(new NamedArguments(array('primaryKey' => $resourceID)));

$orderType = new OrderType(new NamedArguments(array('primaryKey' => $resource->orderTypeID)));
$acquisitionType = new AcquisitionType(new NamedArguments(array('primaryKey' => $resource->acquisitionTypeID)));

//get purchase sites
$sanitizedInstance = array();
$instance = new PurchaseSite();
$purchaseSiteArray = array();
foreach ($resource->getResourcePurchaseSites() as $instance) {
$purchaseSiteArray[]=$instance->shortName;
}


//get payments
$sanitizedInstance = array();
$instance = new ResourcePayment();
$paymentArray = array();
foreach ($resource->getResourcePayments() as $instance) {
	foreach (array_keys($instance->attributeNames) as $attributeName) {
		$sanitizedInstance[$attributeName] = $instance->$attributeName;
	}

	$sanitizedInstance[$instance->primaryKeyName] = $instance->primaryKey;

	$selector = new User(new NamedArguments(array('primaryKey' => $instance->selectorLoginID)));
	$sanitizedInstance['selectorName'] = $selector->firstName . " " . $selector->lastName;

	$orderType = new OrderType(new NamedArguments(array('primaryKey' => $instance->orderTypeID)));
	$sanitizedInstance['orderType'] = $orderType->shortName;


	array_push($paymentArray, $sanitizedInstance);

}


//get license statuses
$sanitizedInstance = array();
$instance = new ResourceLicenseStatus();
$licenseStatusArray = array();
foreach ($resource->getResourceLicenseStatuses() as $instance) {
	foreach (array_keys($instance->attributeNames) as $attributeName) {
		$sanitizedInstance[$attributeName] = $instance->$attributeName;
	}

	$sanitizedInstance[$instance->primaryKeyName] = $instance->primaryKey;

	$changeUser = new User(new NamedArguments(array('primaryKey' => $instance->licenseStatusChangeLoginID)));
	if (($changeUser->firstName) || ($changeUser->lastName)) {
		$sanitizedInstance['changeName'] = $changeUser->firstName . " " . $changeUser->lastName;
	}else{
		$sanitizedInstance['changeName'] = $instance->licenseStatusChangeLoginID;
	}

	$licenseStatus = new LicenseStatus(new NamedArguments(array('primaryKey' => $instance->licenseStatusID)));
	$sanitizedInstance['licenseStatus'] = $licenseStatus->shortName;


	array_push($licenseStatusArray, $sanitizedInstance);

}



//get licenses (already returned in array)
$licenseArray = $resource->getLicenseArray();

?>
<table class='linedFormTable' style='width:460px;'>
<tr>
<th colspan='2' style='vertical-align:bottom;'>
<span style='float:left;vertical-align:bottom;'>Cataloging</span>

<?php if ($user->canEdit()){ ?>
	<span style='float:right;vertical-align:bottom;'><a href='resources/cataloging_edit.php?height=300&width=730&modal=true&resourceID=<?php echo $resourceID; ?>' class='thickbox' id='editOrder'><img src='images/edit.gif' alt='edit' title='edit order information'></a></span>
<?php } ?>

</th>
</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>Identifier:</td>
	<td style='width:350px;'><?php echo $resource->recordSetIdentifier ?></td>
	</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>Source URL:</td>
	<td style='width:350px;'><?php echo $resource->bibSourceURL ?><?php if ($resource->bibSourceURL) { ?> &nbsp;&nbsp;<a href='<?php echo $resource->bibSourceURL; ?>' target='_blank'><img src='images/arrow-up-right.gif' alt='Visit Source URL' title='Visit Source URL' style='vertical-align:top;'></a><?php } ?></td>
	</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>Records Loaded:</td>
	<td style='width:350px;'><?php echo $resource->numberLoaded ?></td>
	</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>Cataloging Type:</td>
	<td style='width:350px;'><?php echo $resource->catalogingType ?></td>
	</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>Cataloging Status:</td>
	<td style='width:350px;'><?php echo $resource->catalogingStatus ?></td>
	</tr>
	<tr>
	<td style='vertical-align:top;width:110px;'>OCLC Holdings:</td>
	<td style='width:350px;'><?php echo $resource->hasOclcHoldings ? 'Yes' : 'No' ?></td>
	</tr>


</table>
<?php if ($user->canEdit()){ ?>
<a href='resources/cataloging_edit.php?height=300&width=730&modal=true&resourceID=<?php echo $resourceID; ?>' class='thickbox'>edit cataloging details</a><br />
<?php } ?>

<br />

<br />



<?php

//get notes for this tab
$sanitizedInstance = array();
$noteArray = array();
foreach ($resource->getNotes('Cataloging') as $instance) {
foreach (array_keys($instance->attributeNames) as $attributeName) {
	$sanitizedInstance[$attributeName] = $instance->$attributeName;
}

$sanitizedInstance[$instance->primaryKeyName] = $instance->primaryKey;

$updateUser = new User(new NamedArguments(array('primaryKey' => $instance->updateLoginID)));

//in case this user doesn't have a first / last name set up
if (($updateUser->firstName != '') || ($updateUser->lastName != '')){
	$sanitizedInstance['updateUser'] = $updateUser->firstName . " " . $updateUser->lastName;
}else{
	$sanitizedInstance['updateUser'] = $instance->updateLoginID;
}

$noteType = new NoteType(new NamedArguments(array('primaryKey' => $instance->noteTypeID)));
if (!$noteType->shortName){
	$sanitizedInstance['noteTypeName'] = 'General Note';
}else{
	$sanitizedInstance['noteTypeName'] = $noteType->shortName;
}

array_push($noteArray, $sanitizedInstance);
}

if (count($noteArray) > 0){
?>
<table class='linedFormTable' style='width:460px;max-width:460px;'>
	<tr>
	<th>Additional Notes</th>
	<th>
	<?php if ($user->canEdit()){?>
		<a href='ajax_forms.php?action=getNoteForm&height=233&width=410&tab=Cataloging&resourceID=<?php echo $resourceID; ?>&resourceNoteID=&modal=true' class='thickbox'>add new note</a>
	<?php } ?>
	</th>
	</tr>
	<?php foreach ($noteArray as $resourceNote){ ?>
		<tr>
		<td style='width:110px;'><?php echo $resourceNote['noteTypeName']; ?><br />
		<?php if ($user->canEdit()){?>
		<a href='ajax_forms.php?action=getNoteForm&height=233&width=410&tab=Cataloging&resourceID=<?php echo $resourceID; ?>&resourceNoteID=<?php echo $resourceNote['resourceNoteID']; ?>&modal=true' class='thickbox'><img src='images/edit.gif' alt='edit' title='edit note'></a>  <a href='javascript:void(0);' class='removeNote' id='<?php echo $resourceNote['resourceNoteID']; ?>' tab='Cataloging'><img src='images/cross.gif' alt='remove note' title='remove note'></a>
		<?php } ?>
		</td>
		<td><?php echo nl2br($resourceNote['noteText']); ?><br /><i><?php echo format_date($resourceNote['updateDate']) . " by " . $resourceNote['updateUser']; ?></i></td>
		</tr>
	<?php } ?>
</table>
<?php
}else{
if ($user->canEdit()){
?>
	<a href='ajax_forms.php?action=getNoteForm&height=233&width=410&tab=Cataloging&resourceID=<?php echo $resourceID; ?>&resourceNoteID=&modal=true' class='thickbox'>add new note</a>
<?php
}
}
?>