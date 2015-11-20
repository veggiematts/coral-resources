<?php

/*
**************************************************************************************************************************
** CORAL Resources Module v. 1.2
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

$cli = false;
$longopts = array(
    "delimiter::",
    "encode",
    "parent::",
    "parentcolumn::",
    "file:",
);

// CSV configuration
$required_columns = array('titleText' => 0, 
    'resourceURL' => 0, 
    'resourceAltURL' => 0, 
    'parentResource' => 0, 
    'organization' => 0, 
    'role' => 0, 
    'title_id' => 0, 
    'dateFirstIssueOnline' => 0, 
    'numFirstVolOnline' => 0,
    'numFirstIssueOnline' => 0,
    'dateLastIssueOnline' => 0,
    'numLastVolOnline' => 0,
    'numLastIssueOnline' => 0,
    'firstAuthor' => 0,
    'embargoInfo' => 0,
    'coverageDepth' => 0, 
    'coverageText' => 0);


$options = getopt("", $longopts);
if ($options) {
  error_reporting(E_ERROR);
  include_once 'directory.php';
  $config = new Configuration();
  $cli = true;
  $encode = array_key_exists('encode', $options);
  $uploadfile = $options['file'];
  echo "file: $uploadfile\n";
  $delimiter = array_key_exists('delimiter', $options) ? $options['delimiter'] : "\t";
  echo "delimiter: $delimiter\n";
  if (($handle = fopen($uploadfile, "r")) !== FALSE) {
    if (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      foreach ($data as $key => $value) {
        $available_columns[$key] = $value;
        foreach ($required_columns as $rkey => $rvalue) {
          if (tryToMatch($value, $rkey)) {
            $_POST[$rkey] = $key;
            echo "Matched $value => $rkey (column $key)\n";
          }
        }
      }
    }
  }
  if (array_key_exists('parent', $options)) {
    $_POST['genericParent'] = $options['parent'];
    echo "Generic parent resource: " . $options['parent'] . "\n";
  }
  if (array_key_exists('parentcolumn', $options)) {
    $_POST['parentResource'] = array_search($options['parentcolumn'], $available_columns);
    echo "Parent resource column: " . $options['parentcolumn'] . " (column " .array_search($options['parentcolumn'], $available_columns) . ")\n";
  }

  $deduping_config = explode(',', $config->settings->importISBNDedupingColumns); 
}

if (!$cli) {
session_start();
include_once 'directory.php';
//print header
$pageTitle=_('Resources import');
include 'templates/header.php';

?><div id="importPage"><h1><?php echo _("CSV File import");?></h1><?php
if ($_POST['submit']) {
  $delimiter = $_POST['delimiter'];
  if ($delimiter == "TAB") $delimiter = "\t";
  $uploaddir = 'attachments/';
  $uploadfile = $uploaddir . basename($_FILES['uploadFile']['name']);
  if (move_uploaded_file($_FILES['uploadFile']['tmp_name'], $uploadfile)) {  
    print '<p>'._("The file has been successfully uploaded.").'</p>';
  
  // Let's analyze this file
  if (($handle = fopen($uploadfile, "r")) !== FALSE) {
    if (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      $columns_ok = true;
      foreach ($data as $key => $value) {
        $available_columns[$value] = $key;
      } 
    } else {
      $error = _("Unable to get columns headers from the file");
    }
  } else {
    $error = _("Unable to open the uploaded file");
  }
  } else {
    $error = _("Unable to upload the file");
  }
  if ($error) {
    print "<p>"._("Error: ").$error.".</p>";
  } else {
    print "<p>"._("Please choose columns from your CSV file:")."</p>";
    print "<form action=\"import.php\" method=\"post\">";
    foreach ($required_columns as $rkey => $rvalue) {
      print "<label for=\"$rkey\">" . $rkey . "</label><select name=\"$rkey\">";
      print '<option value=""></option>';
      foreach ($available_columns as $akey => $avalue) {
        print "<option value=\"$avalue\"";
        if (tryToMatch($akey, $rkey)) print ' selected="selected"';
        print ">$akey</option>";
      } 
      print '</select><br />';
    }
    print "You can also enter a default fallback parent resource: ";
    print "<input type=\"text\" name=\"genericParent\" />";
    print "<input type=\"hidden\" name=\"delimiter\" value=\"$delimiter\" />";
    print "<input type=\"hidden\" name=\"uploadfile\" value=\"$uploadfile\" />";
    print "<input type=\"submit\" name=\"matchsubmit\" id=\"matchsubmit\" /></form>";
  }
}
} else {

}
// Process
if ($_POST['matchsubmit']) {
  $delimiter = $_POST['delimiter'];
  $deduping_config = explode(',', $config->settings->importISBNDedupingColumns); 
  $uploadfile = $_POST['uploadfile'];
}
if ($cli || $_POST['matchsubmit']) {
   // Let's analyze this file
  if (($handle = fopen($uploadfile, "r")) !== FALSE) {
    $row = 0;
    $inserted = 0;
    $parentInserted = 0;
    $parentAttached = 0;
    $organizationsInserted = 0;
    $organizationsAttached = 0;
    $arrayOrganizationsCreated = array();
    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      // Getting column names again for deduping
      if ($row == 0) {
        print "<h2>"._("Settings")."</h2>";
        print "<p>"._("Importing and deduping isbnOrISSN on the following columns: ") ;
        foreach ($data as $key => $value) {
          if (in_array($value, $deduping_config)) {
            $deduping_columns[] = $key;
            print $value . " ";
          }
        } 
        print ".</p>";
      } else {
        // Deduping
        unset($deduping_values);
        $resource = new Resource(); 
        $resourceObj = new Resource(); 
        foreach ($deduping_columns as $value) {
          $deduping_values[] = $data[$value];
        }
        $deduping_count = count($resourceObj->getResourceByIsbnOrISSN($deduping_values));
        if ($deduping_count == 0) {
          // Convert to UTF-8
          if (($cli && $encode) || $_POST['matchsubmit']) 
              $data = array_map(function($row) { return mb_convert_encoding($row, 'UTF-8'); }, $data);
        
          // Let's insert data
          $resource->createLoginID    = $loginID;
          $resource->createDate       = date( 'Y-m-d' );
          $resource->updateLoginID    = '';
          $resource->updateDate       = '';
          $resource->statusID         = 1;
          if ($cli) echo "=> Importing " . $data[$_POST['titleText']] . "\n";
          foreach(array('titleText', 'descriptionText', 'resourceURL', 'resourceAltURL', 'numFirstVolOnline', 'numFirstIssueOnline', 'numLastVolOnline', 'numLastIssueOnline', 'firstAuthor', 'embargoInfo', 'coverageDepth', 'providerText', 'coverageText', 'title_id') as $field) {
            $value = $data[$_POST[$field]];
            if ($value != '') { 
/*
              $encoding = mb_detect_encoding($value);
              $encoding = detectUTF8($value);
              if ($encoding) {
*/
                $resource->$field = $value;
/*
              } else {
                if ($cli) echo "Warning: non-utf8 data ignored ($encoding $value)";
              }
*/
            }
          }

          // TODO: Date handling has to be fixed.
          $resource->dateFirstIssueOnline = $data[$_POST['dateFirstIssueOnline']] ? $data[$_POST['dateFirstIssueOnline']] . "-1-1" : null;
          $resource->dateLastIssueOnline = $data[$_POST['dateLastIssueOnline']] ? $data[$_POST['dateLastIssueOnline']] . "-1-1" : null;

          $resource->save();
          $resource->setIsbnOrIssn($deduping_values);
          $inserted++;
          // Do we have to create an organization or attach the resource to an existing one?
          if ($data[$_POST['organization']]) {
            $organizationName = $data[$_POST['organization']];
            $organization = new Organization();
            $organizationRole = new OrganizationRole();
            $organizationID = false;
            // If we use the Organizations module
            if ($config->settings->organizationsModule == 'Y'){
              
              $dbName = $config->settings->organizationsDatabaseName;
              // Does the organization already exists?
              $query = "SELECT count(*) AS count FROM $dbName.Organization WHERE UPPER(name) = '" . str_replace("'", "''", strtoupper($organizationName)) . "'";
              $result = $organization->db->processQuery($query, 'assoc');
              // If not, we try to create it
              if ($result['count'] == 0) {
                $query = "INSERT INTO $dbName.Organization SET createDate=NOW(), createLoginID='$loginID', name='" . $organization->db->escapeString($organizationName) . "'";
                try {
                  $result = $organization->db->processQuery($query);
                  $organizationID = $result;
                  $organizationsInserted++;
                  array_push($arrayOrganizationsCreated, $organizationName);
                } catch (Exception $e) {
                  print "<p>"._("Organization ").$organizationName._(" could not be added.")."</p>";
                }
              // If yes, we attach it to our resource
              } elseif ($result['count'] == 1) {
                $query = "SELECT name, organizationID FROM $dbName.Organization WHERE UPPER(name) = '" . str_replace("'", "''", strtoupper($organizationName)) . "'";
                $result = $organization->db->processQuery($query, 'assoc');
                $organizationID = $result['organizationID'];
                $organizationsAttached++;
              } else {
                print "<p>"._("Error: more than one organization is called ").$organizationName._(". Please consider deduping.")."</p>";
              }
              if ($organizationID) {
                $dbName = $config->settings->organizationsDatabaseName;
                // Get role
                $query = "SELECT organizationRoleID from OrganizationRole WHERE shortName='" . $organization->db->escapeString($data[$_POST['role']]) . "'";
                $result = $organization->db->processQuery($query);
                // If role is not found, fallback to the first one.
                $roleID = ($result[0]) ? $result[0] : 1;
                // Does the organizationRole already exists?
                $query = "SELECT count(*) AS count FROM $dbName.OrganizationRoleProfile WHERE organizationID=$organizationID AND organizationRoleID=$roleID";
                $result = $organization->db->processQuery($query, 'assoc');
                // If not, we try to create it
                if ($result['count'] == 0) {
                  $query = "INSERT INTO $dbName.OrganizationRoleProfile SET organizationID=$organizationID, organizationRoleID=$roleID";
                  try {
                    $result = $organization->db->processQuery($query);
                    if (!in_array($organizationName, $arrayOrganizationsCreated)) {
                      $organizationsInserted++;
                      array_push($arrayOrganizationsCreated, $organizationName);
                    }
                  } catch (Exception $e) {
                    print "<p>"._("Unable to associate organization ").$organizationName._(" with its role.")."</p>";
                  }
                }
              }
            // If we do not use the Organizations module
            } else {
              // Search if such organization already exists
              $organizationExists = $organization->alreadyExists($organizationName);
              $parentID = null;
              if (!$organizationExists) {
                // If not, create it
                $organization->shortName = $organizationName;
                $organization->save();
                $organizationID = $organization->organizationID();
                $organizationsInserted++;
                array_push($arrayOrganizationsCreated, $organizationName);
              } elseif ($organizationExists == 1) {
                // Else, 
                $organizationID = $organization->getOrganizationIDByName($organizationName);
                $organizationsAttached++;
              } else {
                print "<p>"._("Error: more than one organization is called ").$organizationName._(" Please consider deduping.")."</p>";
              }
              // Find role
              $organizationRoles = $organizationRole->getArray();
              if (($roleID = array_search($data[$_POST['role']], $organizationRoles)) == 0) {
                // If role is not found, fallback to the first one.
                $roleID = '1';
              } 
            }
            // Let's link the resource and the organization.
            // (this has to be done whether the module Organization is in use or not)
            if ($organizationID && $roleID) {
              $organizationLink = new ResourceOrganizationLink();
              $organizationLink->organizationRoleID = $roleID;
              $organizationLink->resourceID = $resource->resourceID;
              $organizationLink->organizationID = $organizationID;
              $organizationLink->save();
            }
          }
        } elseif ($deduping_count == 1) {
          $resources = $resourceObj->getResourceByIsbnOrISSN($deduping_values);
          $resource = $resources[0];
        }
          // Do we have a parent resource to create?
          if ($data[$_POST['parentResource']])
            $parentResourceName = $data[$_POST['parentResource']];
          if ($_POST['genericParent'])
            $parentResourceName = $_POST['genericParent'];

          if ($parentResourceName && ($deduping_count == 0 || $deduping_count == 1) ) {
            // Search if such parent exists
            $numberOfParents = count($resourceObj->getResourceByTitle($parentResourceName));

            $parentID = null;
            if ($numberOfParents == 0) {
              // If not, create parent
              $parentResource = new Resource();
              $parentResource->createLoginID = $loginID;
              $parentResource->createDate    = date( 'Y-m-d' );
              $parentResource->titleText     = $parentResourceName;
              $parentResource->statusID      = 1;
              $parentResource->save();
              $parentID = $parentResource->resourceID;
              $parentInserted++;
            } elseif ($numberOfParents == 1) {
              // Else, attach the resource to its parent.
              $parentResource = $resourceObj->getResourceByTitle($parentResourceName);
              $parentID = $parentResource[0]->resourceID;
              
              $parentAttached++; 
            }
            if ($numberOfParents == 0 || $numberOfParents == 1) {
              $resourceRelationship = new ResourceRelationship();
              $resourceRelationship->resourceID = $resource->resourceID;
              $resourceRelationship->relatedResourceID = $parentID;
              $resourceRelationship->relationshipTypeID = '1';  //hardcoded because we're only allowing parent relationships
              if (!$resourceRelationship->exists()) {
                $resourceRelationship->save();
              }
            }
          } 
        }
      $row++;
    }
    print "<h2>"._("Results")."</h2>";
    print "<p>" . ($row - 1) . _(" rows have been processed. ").$inserted._(" rows have been inserted.")."</p>";
    print "<p>".$parentInserted._(" parents have been created. ").$parentAttached._(" resources have been attached to an existing parent.")."</p>";
    print "<p>".$organizationsInserted._(" organizations have been created");
    if (count($arrayOrganizationsCreated) > 0) print " (" . implode(',', $arrayOrganizationsCreated) . ")";
    print ". $organizationsAttached"._(" resources have been attached to an existing organization.")."</p>";
  }
} else {
          
?>
<p><?php echo _("The first line of the CSV file must contain column names, and not data. These names will be used during the import process.");?></p>
<form enctype="multipart/form-data" action="import.php" method="post" id="importForm">
  <fieldset>
  <legend><?php echo _("File selection");?></legend>
  <label for="uploadFile"><?php echo _("CSV File");?></label>
  <input type="file" name="uploadFile" id="uploadFile" />
  </fieldset>
  <fieldset>
  <legend><?php echo _("Import options");?></legend>
  <label for="CSV delimiter"><?php echo _("CSV delimiter");?></label>
  <select name="delimiter">
    <option value=",">, <?php echo _("(comma)");?></option>
    <option value=";">; <?php echo _("(semicolon)");?></option>
    <option value="|">| <?php echo _("(pipe)");?></option>
    <option value="TAB"><?php echo _("tabulation");?></option>
  </select>
  </fieldset>
  <input type="submit" name="submit" value="<?php echo _("Upload");?>" />
</form>

<?php
}
?>
</div>
<?php
//print footer
include 'templates/footer.php';

  function tryToMatch($csv, $coral) {
    return ($csv == $coral || camelize($csv) == $coral || kbartMatching($csv) == $coral); 
  }

  function camelize($scored) {
    return lcfirst(
      implode(
        '',
        array_map(
          'ucfirst',
          array_map(
            'strtolower',
            explode(
              '_', $scored)))));
  }

  function kbartMatching($csv) {
    $kbartMatching = array('publication_title' => 'titleText',
    'title_url' => 'resourceURL',
    'publisher_name' => 'organization',
    'print_identifier' => 'isbnOrIssn',
    'online_identifier' => 'isbnOrIssn', 
    'coverage_notes' => 'coverageText');
    return (array_key_exists($csv, $kbartMatching) ? $kbartMatching[$csv] : null);
  }

  function extractColumns($line) {

  }

function detectUTF8($string)
{
        return preg_match('%(?:
        [\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
        |\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
        |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
        |\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
        |\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
        |[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
        |\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
        )+%xs', $string);
}
?>
