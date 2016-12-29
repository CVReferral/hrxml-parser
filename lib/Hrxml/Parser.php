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
    return $this->parseString($xmlString);
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
			$dobTime = self::parseDateString($dob);
			if (count($dobTime) > 0 && strlen($dobTime[count($dobTime) - 1]) == 4) {
				$candidate->birth_year = $dobTime[count($dobTime) - 1];
			}
		}
		$gender = strtolower(self::getNodeValue($xml, 'Gender'));
		switch ($gender) {
			case 'female':
				$gender = 'Nữ';
				break;
			case 'male':
				$gender = 'Nam';
				break;
		}
		$candidate->gender = $gender;

		//TODO: should store to S3
		$candidate->avatar = self::getNodeValue($xml->CandidateImage, 'CandidateImageData');
		
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
			
			$startDate = self::getNodeValue($xmlWork, 'StartDate');
			$startDateTime = false;
			if ($startDate) {
				$startDateTime = self::parseDateString($startDate);
			}
			if (count($startDateTime) > 0) {
				$work->from = implode('/', [$startDateTime[count($startDateTime) - 2], $startDateTime[count($startDateTime) - 1]]);
			}
			
			$jobPeriod = self::getNodeValue($xmlWork, 'JobPeriod');
			$endDate = self::getNodeValue($xmlWork, 'EndDate');
			if ($jobPeriod && count(explode(' - ', $jobPeriod)) == 2 && strtolower(explode(' - ', $jobPeriod)[1]) == 'till') {
				$work->is_present = 1;
				$work->to = 'Đến nay';
			} elseif ($endDate) {
				$endDateTime = self::parseDateString($endDate);
				if (count($endDateTime) > 0) {
					$work->to = implode('/', [$endDateTime[count($endDateTime) - 2], $endDateTime[count($endDateTime) - 1]]);
				}
			}
		
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

	public static function parseDateString($string, $separator = '/') {
		$arr = explode($separator, $string);
		return (count($arr) >= 2) ? $arr : [];
	}
}
