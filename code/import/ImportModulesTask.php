<?php



class ImportModulesTask extends BuildTask{

	static $register_group_title = "Software Authors";
		function set_register_group_title($s) {self::$register_group_title = $s;}

	static $register_group_code = "softwareauthors";
		function set_register_group_code($s) {self::$register_group_code = $s;}

	static $register_group_access_key = "SOFTWAREAUTHORS";
		function set_register_group_access_key($s) {self::$register_group_access_key = $s;}

	function getTitle() {
		return "Import modules from csv";
	}

	function getDescription() {
		return "Opens the local csv file and imports all the modules";
	}


	function run($request) {
		return $this->importmodules();
	}
	function cleantags(){
		DB::query("DELETE FROM \"EcommerceProductTag\" WHERE TRIM(Title) = '' OR TRIM(Code) = '' OR Title IS NULL or Code IS NULL;");
	}

	function importmodules() {
		$rowPosition = 1;
		$fields = array(
			0 => "ImportID",
			1 => "ScreenName",
			2 => "Email",
			3 => "GithubURL",
			4 => "SilverstripeDotOrgURL",
			5 => "CompanyName",
			6 => "CompanyURL",
			7 => "Code",
			8 => "Title",
			9=> "MainURL",
			10=> "ReadMeURL",
			11=> "DemoURL",
			12=> "SvnURL",
			13=> "GitURL",
			14=> "OtherURL",
			15=> "Tags",
			16=> "Description"
		);
		$fullArray = array();
		$file = Director::baseFolder()."/mysite/data/modules.csv";
		if (($handle = fopen($file, "r")) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
				$numberOfFields = count($row);
				//echo "<p> $num fields in line $rowPosition: <br /></p>\n";
				$rowPosition++;
				for ($fieldPosition = 0; $fieldPosition < $numberOfFields; $fieldPosition++) {
					$fullArray[$rowPosition][$fields[$fieldPosition]] = $row[$fieldPosition];
					//echo $fields[$fieldPosition]." = ".$row[$fieldPosition] . "<br />\n";
				}
			}
			fclose($handle);
		}
		$this->makeModules($fullArray);
		return $fullArray;
	}



	private function makeModules($rows)  {
		increase_time_limit_to(600);
		$parent = DataObject::get_one("ModuleProductGroup", "\"URLSegment\" = 'new-modules'");
		DB::query("DELETE FROM \"EcommerceProductTag\" WHERE TRIM(Title) = '';");
		if($parent) {
			$parentID = $parent->ID;
			unset($parent);
		}
		else {
			$parentID = 0;
		}
		if(!$group = DataObject::get_one("Group", "Code = '".self::$register_group_code."'")) {
			$group = new Group();
			$group->Code = self::$register_group_code;
			$group->Title = self::$register_group_title;
			$group->write();
			Permission::grant( $group->ID, self::$register_group_access_key);
			DB::alteration_message("GROUP: ".self::$register_group_code.' ('.self::$register_group_title.')' ,"created");
		}
		elseif(DB::query("SELECT * FROM Permission WHERE GroupID = ".$group->ID." AND Code = '".self::$register_group_access_key."'")->numRecords() == 0) {
			Permission::grant($group->ID, self::$register_group_access_key);
		}
		if($parentID) {
			if($rows) {
				foreach($rows as $row) {
					$new = null;
					$ImportID = intval($row["ImportID"]);
					if($ImportID) {
						$ScreenName = Convert::raw2sql($row["ScreenName"]);
						$Email = Convert::raw2sql($row["ScreenName"]);
						$GithubURL = Convert::raw2sql($row["GithubURL"]);
						$SilverstripeDotOrgURL = Convert::raw2sql($row["SilverstripeDotOrgURL"]);
						$CompanyName = Convert::raw2sql($row["CompanyName"]);
						$CompanyURL = Convert::raw2sql($row["CompanyURL"]);
						$Code = Convert::raw2sql($row["Code"]);
						$Title = Convert::raw2sql($row["Title"]);
						$MainURL = Convert::raw2sql($row["MainURL"]);
						$ReadMeURL = Convert::raw2sql($row["ReadMeURL"]);
						$DemoURL = Convert::raw2sql($row["DemoURL"]);
						$SvnURL = Convert::raw2sql($row["SvnURL"]);
						$GitURL = Convert::raw2sql($row["GitURL"]);
						$OtherURL = Convert::raw2sql($row["OtherURL"]);
						$Tags =  Convert::raw2sql($row["Tags"]);
						$Description =  Convert::raw2sql($row["Description"]);
						$page = DataObject::get_one("ModuleProduct", "\"ImportID\" = '".$ImportID."'");
						if(!$page) {
							$new = true;
							$page = new ModuleProduct();
						}
						else {
							$new = false;
							DB::query("DELETE FROM \"EcommerceProductTag_Products\" WHERE ProductID = ".$page->ID);
						}
						if($Title && $Code) {
							//member
							$member = DataObject::get_one("Member", "ScreenName = '$ScreenName'");
							if($member){
								DB::query("DELETE FROM \"ModuleProduct_Authors\" WHERE MemberID = ".$member->ID);
							}
							else {
								$member = new Member();
							}
							$member->ScreenName = $ScreenName;
							$member->Email = $Email;
							$member->GithubURL = $GithubURL;
							$member->SilverstripeDotOrgURL = $SilverstripeDotOrgURL;
							$member->CompanyName = $CompanyName;
							$member->CompanyURL = $CompanyURL;
							$member->write();
							$member->Groups()->add($group);
							//page
							$page->ParentID = $parentID;
							$page->ImportID = $ImportID;
							$page->Title = $Title;
							$page->MetaTitle = $Title;
							$page->MenuTitle = $Title;
							$page->Code = $Code;
							$page->InternalItemID = $Code;
							$page->URLSegment = $Code;

							$page->MainURL = $MainURL;
							$page->ReadMeURL = $ReadMeURL;
							$page->DemoURL= $DemoURL;
							$page->SvnURL = $SvnURL;
							$page->GitURL = $GitURL;
							$page->OtherURL = $OtherURL;
							$page->Content = "<p>".strip_tags($Description)."</p>";
							$page->writeToStage('Stage');
							$page->Publish('Stage', 'Live');
							$page->Status = "Published";
							$tagsArray = explode(",", $Tags);
							if($tagsArray && count($tagsArray)) {
								foreach($tagsArray as $tag) {
									$tag = Convert::raw2sql(trim($tag));
									if($tag) {
										$tagObject = DataObject::get_one("EcommerceProductTag", "Title = '$tag'");
										if(!$tagObject) {
											$tagObject = new EcommerceProductTag();
											$tagObject->Title = $tag;
											$tagObject->write();
										}
										$existingTags = $page->EcommerceProductTags();
										$existingTags->add($tagObject);
									}
								}
							}
							$existingAuthors = $page->Authors();
							$existingAuthors->add($member);
							if($new === true) {
								DB::alteration_message("added <b>$Title</b>", "created");
							}
							elseif($new === false)  {
								DB::alteration_message("updated <b>$Title</b>", "edited");
							}
							elseif($new === null){
								DB::alteration_message("error updating <b>$Title</b>", "deleted");
							}
							else {
								DB::alteration_message("BIG error updating <b>$Title</b>", "deleted");
							}
						}
						else {
							DB::alteration_message("row found without title or code", "deleted");
						}
					}
					else {
						DB::alteration_message("row found without import id", "deleted");
					}
				}
			}
			else {
				DB::alteration_message("no data found", "deleted");
			}
		}
		else {
			DB::alteration_message("no parent group page found", "deleted");
		}
	}
}







class ImportModulesTask_AdminDecorator extends Extension{

	static $allowed_actions = array(
		"importmodulestask" => true
	);

	function updateEcommerceDevMenuMigrations(&$buildTasks){
		$buildTasks[] = "importmodulestask";
		return $buildTasks;
	}


	/**
	 * executes build task: ImportModulesTask
	 *
	 */
	public function importmodulestask($request) {
		$buildTask = new ImportModulesTask($request);
		$buildTask->run($request);
		$this->owner->displayCompletionMessage($buildTask);
	}



}
