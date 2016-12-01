<?php
namespace Hrxml;

class Parser {

  private static $instance = null;

  public static function getInstance() {
    if (self::$instance == null) {
      self::$instance = new Parser();
    }

    return self::$instance;
  }

  public function parseFile($uri) {
    $xmlString = file_get_contents($uri);
    return $this->parse($xmlString);
  }

  public function parseString($xmlString) {
    $xml = simplexml_load_string($xmlString);
    return $this->parse($xml);
  }

	public function parse($xml) {
		$candidate = new \StdClass;
		$candidate->first_name = self::getNodeValue($xml, 'FirstName');
		$candidate->last_name = self::getNodeValue($xml, 'LastName');
		$candidate->email = self::getNodeValue($xml, 'Email', 'AlternateEmail');
		$candidate->phone = self::getNodeValue($xml, 'Phone', 'FormattedPhone');
		if (!$candidate->phone) {
			$candidate->phone = self::getNodeValue($xml, 'Mobile', 'FormattedMobile');
		}
		$dob = self::getNodeValue($xml, 'DateOfBirth');
		if ($dob) {
			$candidate->birth_year = date('Y', strtotime($dob));
		}
		$candidate->gender = null;
		//t		here is no gener field
		    $candidate->avatar = self::getNodeValue($xml->CandidateImage, 'CandidateImageData');
		//T		ODO: should store to S3
		
		// 		Candidate Education
		    // 		EducationSplit block repeats as many as value found in resume for it
		    $candidate->education_list = [];
		$i = 0;
		foreach ($xml->SegregatedQualification->EducationSplit as $xmlEdu) {
			$edu = new \StdClass;
			$edu->id = 'edu-' . $i;
			$edu->school = self::getNodeValue($xmlEdu, 'UniversityName');
			$edu->degree = self::getNodeValue($xmlEdu, 'Degree');
			$edu->to = self::getNodeValue($xmlEdu, 'Year');
			
			$candidate->education_list []= $edu;
			$i++;
		}
		
		// 		Candidates Working Experience Drilldown
		    // 		$candidate->working_list = [];
		$i = 0;
		foreach ($xml->SegregatedExperience->WorkHistory as $xmlWork) {
			$work = new \StdClass;
			$work->id = 'work-' . $i;
			$work->company = self::getNodeValue($xmlWork, 'Employer');
			$work->position = self::getNodeValue($xmlWork, 'JobProfile');
			$work->description = self::getNodeValue($xmlWork, 'JobDescription');
			
			$candidate->working_list []= $work;
			$i++;
		}
		
		// extract skills
    $behaviorSkills = $this->extractSkills($xml->skillskeywords, 'BehaviorSkills');
		$softSkills = $this->extractSkills($xml->skillskeywords, 'SoftSkills');
		$operationalSkills = $this->extractSkills($xml->skillskeywords, 'OperationalSkills');
		$candidate->skills = implode(', ', array_merge($behaviorSkills, $softSkills, $operationalSkills));
		
		return $candidate;
	}
	
  /**
  * 
  */
  protected function extractSkills($xml, $skillType) {
    $skills = [];
    foreach ($xml->{$skillType}->SkillSet as $xmlSkill) {
      $skills []= self::getNodeValue($xmlSkill, 'Skill');
    }
    return $skills;
  }

  public static function getNodeValue($xml, $node, $altNode = false) {
		$val = strip_tags(trim((string) $xml->$node));
		if (strlen($val)) {
			return $val;
		}
		else if ($altNode) {
			$val = strip_tags(trim((string) $xml->$altNode));
		}
		return strlen($val) ? $val : null;
	}
}
