<?php

namespace stekycz\Cronner\Tasks;

use Nette\Object;
use stekycz\Cronner\InvalidParameter;
use Nette\DateTime;
use Nette\Utils\Strings;

/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 * @since 2013-02-04
 */
class Parser extends Object {

	/**
	 * Parses name of cron task.
	 *
	 * @param string $annotation
	 * @return string|null
	 */
	public static function parseName($annotation) {
		$name = null;
		if (is_string($annotation) && Strings::length($annotation)) {
			$name = Strings::trim($annotation);
			$name = Strings::length($name) ? $name : null;
		}
		return $name;
	}

	/**
	 * Parses period of cron task. If annotation is invalid throws exception.
	 *
	 * @param string $annotation
	 * @return string|null
	 * @throws \stekycz\Cronner\InvalidParameter
	 */
	public static function parsePeriod($annotation) {
		$period = null;
		static::checkAnnotation($annotation);
		$annotation = Strings::trim($annotation);
		if (Strings::length($annotation)) {
			if (strtotime('+ ' . $annotation) === false) {
				throw new InvalidParameter("Given period parameter '" . $annotation . "' must be valid for strtotime().");
			}
			$period = $annotation;
		}
		return $period ?: null;
	}

	/**
	 * Parses allowed days for cron task. If annotation is invalid
	 * throws exception.
	 *
	 * @param string $annotation
	 * @return string[]|null
	 * @throws \stekycz\Cronner\InvalidParameter
	 */
	public static function parseDays($annotation) {
		static $validValues = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', );

		$days = null;
		static::checkAnnotation($annotation);
		$annotation = Strings::trim($annotation);
		if (Strings::length($annotation)) {
			$days = static::translateToDayNames($annotation);
			foreach ($days as $day) {
				if (!in_array($day, $validValues)) {
					throw new InvalidParameter(
						"Given day parameter '" . $day . "' must be one from " . implode(', ', $validValues) . "."
					);
				}
			}
			$days = array_values(array_intersect($validValues, $days));
		}
		return $days ?: null;
	}

	/**
	 * Parses allowed time ranges for cron task. If annotation is invalid
	 * throws exception.
	 *
	 * @param string $annotation
	 * @return string[][]|null
	 * @throws \stekycz\Cronner\InvalidParameter
	 */
	public static function parseTimes($annotation) {
		$times = null;
		static::checkAnnotation($annotation);
		$annotation = Strings::trim($annotation);
		if (Strings::length($annotation)) {
			if ($values = static::splitMultipleValues($annotation)) {
				$times = array();
				foreach ($values as $time) {
					$times = array_merge($times, static::parseOneTime($time));
				}
				usort($times, function ($a, $b) {
					return $a < $b ? -1 : ($a > $b ? 1 : 0);
				});
			}
		}
		return $times ?: null;
	}

	private static function translateToDayNames($annotation) {
		static $workingDays = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', );
		static $weekend = array('Sat', 'Sun', );

		$days = array();
		foreach (static::splitMultipleValues($annotation) as $value) {
			switch ($value) {
				case 'working days':
					$days = array_merge($days, $workingDays);
					break;
				case 'weekend':
					$days = array_merge($days, $weekend);
					break;
				default:
					$days[] = $value;
					break;
			}
		}
		return array_unique($days);
	}

	/**
	 * Splits given annotation by comma into array.
	 *
	 * @param $annotation
	 * @return string[]
	 */
	private static function splitMultipleValues($annotation) {
		return Strings::split($annotation, '/\s*,\s*/');
	}

	/**
	 * Returns True if time in valid format is given, False otherwise.
	 *
	 * @param string $time
	 * @return bool
	 */
	private static function isValidTime($time) {
		return (bool) Strings::match($time, '/^\d{2}:\d{2}$/u');
	}

	/**
	 * Parses one time annotation. If it is invalid throws exception.
	 *
	 * @param string $time
	 * @return string[][]
	 * @throws \stekycz\Cronner\InvalidParameter
	 */
	private static function parseOneTime($time) {
		$parts = Strings::split($time, '/\s*-\s*/');
		if (!static::isValidTime($parts[0]) || (isset($parts[1]) && !static::isValidTime($parts[1]))) {
			throw new InvalidParameter(
				"Times annotation is not in valid format. It must looks like 'hh:mm[ - hh:mm]' but '" . $time . "' was given."
			);
		}
		$times = array();
		if (static::isTimeOverMidnight($parts[0], $parts[1])) {
			$times[] = static::timePartsToArray('00:00', $parts[1]);
			$times[] = static::timePartsToArray($parts[0], '23:59');
		} else {
			$times[] = static::timePartsToArray($parts[0], $parts[1] ?: null);
		}
		return $times;
	}

	/**
	 * Returns True if given times includes midnight, False otherwise.
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	private static function isTimeOverMidnight($from, $to) {
		return $to !== null && $to < $from;
	}

	/**
	 * Returns array structure with given times.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array
	 */
	private static function timePartsToArray($from, $to) {
		return array(
			'from' => $from,
			'to' => $to,
		);
	}

	/**
	 * Checks if given annotation is valid. Throws exception if not.
	 *
	 * @param string $annotation
	 * @throws \stekycz\Cronner\InvalidParameter
	 */
	private static function checkAnnotation($annotation) {
		if (!is_string($annotation)) {
			throw new InvalidParameter(
				"Cron task annotation must be string but '" .
					is_object($annotation) ? get_class($annotation) : gettype($annotation) . "' given."
			);
		}
	}

}