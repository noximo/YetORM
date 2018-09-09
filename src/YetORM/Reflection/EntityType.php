<?php

/**
 * This file is part of the YetORM package
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/YetORM
 */

namespace YetORM\Reflection;

use YetORM;
use Nette\Utils\Strings as NStrings;
use Nette\Reflection\Method as NMethod;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType as NClassType;


class EntityType extends NClassType
{

	/** @var EntityProperty[]|NULL */
	private $properties = NULL;

	/** @var array <class> => AnnotationProperty[] */
	private static $annProps = [];


	/** @return EntityProperty[] */
	public function getEntityProperties()
	{
		$this->loadEntityProperties();
		return $this->properties;
	}


	/**
	 * @param  string $name
	 * @return EntityProperty|NULL
	 */
	public function getEntityProperty($name, $default = NULL)
	{
		return $this->hasEntityProperty($name) ? $this->properties[$name] : $default;
	}


	/**
	 * @param  string $name
	 * @return bool
	 */
	public function hasEntityProperty($name)
	{
		$this->loadEntityProperties();
		return isset($this->properties[$name]);
	}


	/** @return void */
	private function loadEntityProperties()
	{
		if ($this->properties === NULL) {
			$this->properties = [];
			$this->loadMethodProperties();

			foreach ($this->getClassTree() as $class) {
				self::loadAnnotationProperties($class);

				foreach (self::$annProps[$class] as $name => $property) {
					if (!isset($this->properties[$name])) {
						$this->properties[$name] = $property;
					}
				}
			}
		}
	}


	/** @return void */
	private function loadMethodProperties()
	{
		foreach ($this->getMethods(NMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== 'YetORM\\Entity'
					&& strlen($method->getName()) > 3 && substr($method->getName(), 0, 3) === 'get'
					&& !$method->hasAnnotation('internal')) {

				$name = lcfirst(substr($method->getName(), 3));
				$type = $method->getAnnotation('return');

				if ($type !== NULL && !EntityProperty::isNativeType($type)) {
					$type = AnnotationsParser::expandClassName($type, $this);
				}

				$description = trim(preg_replace('#^\s*@.*#m', '', preg_replace('#^\s*\* ?#m', '', trim($method->getDocComment(), "/* \r\n\t"))));

				$this->properties[$name] = new MethodProperty(
					$this,
					$name,
					!$this->hasMethod('set' . ucfirst($name)),
					$type,
					strlen($description) ? $description : NULL
				);
			}
		}
	}


	/** @return array */
	private function getClassTree()
	{
		$tree = [];
		$current = $this->getName();

		do {
			$tree[] = $current;
			$current = get_parent_class($current);

		} while ($current !== FALSE && $current !== YetORM\Entity::class);

		return array_reverse($tree);
	}


	/**
	 * @param  string $class
	 * @return void
	 */
	private static function loadAnnotationProperties($class)
	{
		if (!isset(self::$annProps[$class])) {
			self::$annProps[$class] = [];
			$ref = $class::getReflection();

			foreach ($ref->getAnnotations() as $ann => $values) {
				if ($ann === 'property' || $ann === 'property-read') {
					foreach ($values as $line) {
					    $matches = NStrings::match($line, '#^[ \t]*(?P<type>\\\\?[a-zA-Z_\x7f-\xff][\[\]|a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*(?:\|\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*)?)[ \t]+(?P<property>[\$a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(?:[ \t]+->[ \t]+(?P<column>[a-zA-Z0-9_-]+))?[ \t]*(?P<description>.*)\z#');
                        if ($matches === NULL) {
							throw new YetORM\Exception\InvalidPropertyDefinitionException('"@property[-read] <type> $<property> [-> <column>][ <description>]" expected, "@' . $ann . ' ' . $line . '" given.');
						}

						if ($matches['property'][0] !== '$') {
							throw new YetORM\Exception\InvalidPropertyDefinitionException('Missing "$" in property name in "@' . $ann . ' ' . $line . '"');
						}

						$nullable = FALSE;
						$type = $matches['type'];

						$types = explode('|', $type, 2);
						if (count($types) === 2) {
							if (strcasecmp($types[0], 'NULL') === 0) {
								$nullable = TRUE;
								$type = $types[1];
							}

							if (strcasecmp($types[1], 'NULL') === 0) {
								if ($nullable) {
									throw new YetORM\Exception\InvalidPropertyDefinitionException('Only one NULL is allowed, "' . $matches['type'] . '" given.');
								}

								$nullable = TRUE;
								$type = $types[0];
							}

							if (!$nullable) {
                                $type = $types[0];
								//throw new YetORM\Exception\InvalidPropertyDefinitionException('Multiple non-NULL types detected.');
							}
						}

						if ($type === 'boolean') {
							$type = 'bool';

						} elseif ($type === 'integer') {
							$type = 'int';
						}

						if (!EntityProperty::isNativeType($type)) {
							$type = AnnotationsParser::expandClassName($type, $ref);
						}

						$readonly = $ann === 'property-read';
						$name = substr($matches['property'], 1);
						$column = strlen($matches['column']) ? $matches['column'] : $name;
						$description = strlen($matches['description']) ? $matches['description'] : NULL;

						self::$annProps[$class][$name] = new AnnotationProperty(
								$ref,
								$name,
								$readonly,
								$type,
								$column,
								$nullable,
								$description
						);
					}
				}
			}
		}
	}

}
