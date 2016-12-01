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
			$dobTime = self::parseDateString($dob, '/');
			if (!$dobTime) {
				$dobTime = self::parseDateString($dob, '-');
			}
			if ($dobTime) {
				$candidate->birth_year = date('Y', $dobTime);
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
		$candidate->sex = $gender;

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
			if ($startDate) {
				$startDateTime = self::parseDateString($startDate, '/');
			}
			if (!$startDateTime) {
				$startDateTime = self::parseDateString($startDate, '-');
			}
			if ($startDateTime) {
				$work->from = date('m/Y', $startDateTime);
			}

			$endDate = self::getNodeValue($xmlWork, 'EndDate');
			if (strtolower($endDate) === 'till') {
				$work->to = 'Hiện tại';
			} elseif ($endDate) {
				$endDateTime = self::parseDateString($endDate, '/');
				if (!$endDateTime) {
					$endDateTime = self::parseDateString($endDate, '-');
				}
				if ($endDateTime) {
					$work->to = date('m/Y', $endDateTime);
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
		$time = strtotime($string);
		if ($time) {
			return $time;
		} else {
			$arr = explode($separator, $string);
			if (count($arr) === 3) {
				return self::parseDateString(implode('-', [$arr[2], $arr[1], $arr[0]]));
			}
		}
		return false;
	}
}
