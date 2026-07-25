<?php
// sabre/vobject 4.5.7 <https://sabre.io>
// Copyright Fruux, Modified BSD License.

// Vendored because I hate PHP package management.
// Sorry!

namespace Sabre\VObject;

/**
 * Exception thrown by Reader if an invalid object was attempted to be parsed.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ParseException extends \Exception
{
}

namespace Sabre\VObject;

/**
 * Exception thrown by parser when the end of the stream has been reached,
 * before this was expected.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class EofException extends ParseException
{
}

namespace Sabre\VObject;

/**
 * This exception is thrown whenever an invalid value is found anywhere in a
 * iCalendar or vCard object.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class InvalidDataException extends \Exception
{
}

namespace Sabre\VObject;

/**
 * A node is the root class for every element in an iCalendar of vCard object.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
abstract class Node implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
    /**
     * The following constants are used by the validate() method.
     *
     * If REPAIR is set, the validator will attempt to repair any broken data
     * (if possible).
     */
    const REPAIR = 1;

    /**
     * If this option is set, the validator will operate on the vcards on the
     * assumption that the vcards need to be valid for CardDAV.
     *
     * This means for example that the UID is required, whereas it is not for
     * regular vcards.
     */
    const PROFILE_CARDDAV = 2;

    /**
     * If this option is set, the validator will operate on iCalendar objects
     * on the assumption that the vcards need to be valid for CalDAV.
     *
     * This means for example that calendars can only contain objects with
     * identical component types and UIDs.
     */
    const PROFILE_CALDAV = 4;

    /**
     * Reference to the parent object, if this is not the top object.
     *
     * @var Node
     */
    public $parent;

    /**
     * Iterator override.
     *
     * @var ElementList
     */
    protected $iterator = null;

    /**
     * The root document.
     *
     * @var Component
     */
    protected $root;

    /**
     * Serializes the node into a mimedir format.
     *
     * @return string
     */
    abstract public function serialize();

    /**
     * This method returns an array, with the representation as it should be
     * encoded in JSON. This is used to create jCard or jCal documents.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    abstract public function jsonSerialize();

    /**
     * Call this method on a document if you're done using it.
     *
     * It's intended to remove all circular references, so PHP can easily clean
     * it up.
     */
    public function destroy()
    {
        $this->parent = null;
        $this->root = null;
    }

    /* {{{ IteratorAggregator interface */

    /**
     * Returns the iterator for this object.
     *
     * @return ElementList
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        if (!is_null($this->iterator)) {
            return $this->iterator;
        }

        return new ElementList([$this]);
    }

    /**
     * Sets the overridden iterator.
     *
     * Note that this is not actually part of the iterator interface
     */
    public function setIterator(ElementList $iterator)
    {
        $this->iterator = $iterator;
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   Node::REPAIR - May attempt to automatically repair the problem.
     *
     * This method returns an array with detected problems.
     * Every element has the following properties:
     *
     *  * level - problem level.
     *  * message - A human-readable string describing the issue.
     *  * node - A reference to the problematic node.
     *
     * The level means:
     *   1 - The issue was repaired (only happens if REPAIR was turned on)
     *   2 - An inconsequential issue
     *   3 - A severe issue.
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        return [];
    }

    /* }}} */

    /* {{{ Countable interface */

    /**
     * Returns the number of elements.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        $it = $this->getIterator();

        return $it->count();
    }

    /* }}} */

    /* {{{ ArrayAccess Interface */

    /**
     * Checks if an item exists through ArrayAccess.
     *
     * This method just forwards the request to the inner iterator
     *
     * @param int $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        $iterator = $this->getIterator();

        return $iterator->offsetExists($offset);
    }

    /**
     * Gets an item through ArrayAccess.
     *
     * This method just forwards the request to the inner iterator
     *
     * @param int $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $iterator = $this->getIterator();

        return $iterator->offsetGet($offset);
    }

    /**
     * Sets an item through ArrayAccess.
     *
     * This method just forwards the request to the inner iterator
     *
     * @param int   $offset
     * @param mixed $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $iterator = $this->getIterator();
        $iterator->offsetSet($offset, $value);

        // @codeCoverageIgnoreStart
    //
    // This method always throws an exception, so we ignore the closing
    // brace
    }

    // @codeCoverageIgnoreEnd

    /**
     * Sets an item through ArrayAccess.
     *
     * This method just forwards the request to the inner iterator
     *
     * @param int $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $iterator = $this->getIterator();
        $iterator->offsetUnset($offset);

        // @codeCoverageIgnoreStart
    //
    // This method always throws an exception, so we ignore the closing
    // brace
    }

    // @codeCoverageIgnoreEnd

    /* }}} */
}

namespace Sabre\VObject;

use ArrayIterator;
use LogicException;

/**
 * VObject ElementList.
 *
 * This class represents a list of elements. Lists are the result of queries,
 * such as doing $vcalendar->vevent where there's multiple VEVENT objects.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ElementList extends ArrayIterator
{
    /* {{{ ArrayAccess Interface */

    /**
     * Sets an item through ArrayAccess.
     *
     * @param int   $offset
     * @param mixed $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new LogicException('You can not add new objects to an ElementList');
    }

    /**
     * Sets an item through ArrayAccess.
     *
     * This method just forwards the request to the inner iterator
     *
     * @param int $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new LogicException('You can not remove objects from an ElementList');
    }

    /* }}} */
}

namespace Sabre\VObject;

use Sabre\Xml;

/**
 * Component.
 *
 * A component represents a group of properties, such as VCALENDAR, VEVENT, or
 * VCARD.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Component extends Node
{
    /**
     * Component name.
     *
     * This will contain a string such as VEVENT, VTODO, VCALENDAR, VCARD.
     *
     * @var string
     */
    public $name;

    /**
     * A list of properties and/or sub-components.
     *
     * @var array<string, Component|Property>
     */
    protected $children = [];

    /**
     * Creates a new component.
     *
     * You can specify the children either in key=>value syntax, in which case
     * properties will automatically be created, or you can just pass a list of
     * Component and Property object.
     *
     * By default, a set of sensible values will be added to the component. For
     * an iCalendar object, this may be something like CALSCALE:GREGORIAN. To
     * ensure that this does not happen, set $defaults to false.
     *
     * @param string|null $name     such as VCALENDAR, VEVENT
     * @param bool        $defaults
     */
    public function __construct(Document $root, $name, array $children = [], $defaults = true)
    {
        $this->name = isset($name) ? strtoupper($name) : '';
        $this->root = $root;

        if ($defaults) {
            // This is a terribly convoluted way to do this, but this ensures
            // that the order of properties as they are specified in both
            // defaults and the childrens list, are inserted in the object in a
            // natural way.
            $list = $this->getDefaults();
            $nodes = [];
            foreach ($children as $key => $value) {
                if ($value instanceof Node) {
                    if (isset($list[$value->name])) {
                        unset($list[$value->name]);
                    }
                    $nodes[] = $value;
                } else {
                    $list[$key] = $value;
                }
            }
            foreach ($list as $key => $value) {
                $this->add($key, $value);
            }
            foreach ($nodes as $node) {
                $this->add($node);
            }
        } else {
            foreach ($children as $k => $child) {
                if ($child instanceof Node) {
                    // Component or Property
                    $this->add($child);
                } else {
                    // Property key=>value
                    $this->add($k, $child);
                }
            }
        }
    }

    /**
     * Adds a new property or component, and returns the new item.
     *
     * This method has 3 possible signatures:
     *
     * add(Component $comp) // Adds a new component
     * add(Property $prop)  // Adds a new property
     * add($name, $value, array $parameters = []) // Adds a new property
     * add($name, array $children = []) // Adds a new component
     * by name.
     *
     * @return Node
     */
    public function add()
    {
        $arguments = func_get_args();

        if ($arguments[0] instanceof Node) {
            if (isset($arguments[1])) {
                throw new \InvalidArgumentException('The second argument must not be specified, when passing a VObject Node');
            }
            $arguments[0]->parent = $this;
            $newNode = $arguments[0];
        } elseif (is_string($arguments[0])) {
            $newNode = call_user_func_array([$this->root, 'create'], $arguments);
        } else {
            throw new \InvalidArgumentException('The first argument must either be a \\Sabre\\VObject\\Node or a string');
        }

        $name = $newNode->name;
        if (isset($this->children[$name])) {
            $this->children[$name][] = $newNode;
        } else {
            $this->children[$name] = [$newNode];
        }

        return $newNode;
    }

    /**
     * This method removes a component or property from this component.
     *
     * You can either specify the item by name (like DTSTART), in which case
     * all properties/components with that name will be removed, or you can
     * pass an instance of a property or component, in which case only that
     * exact item will be removed.
     *
     * @param string|Property|Component $item
     */
    public function remove($item)
    {
        if (is_string($item)) {
            // If there's no dot in the name, it's an exact property name and
            // we can just wipe out all those properties.
            //
            if (false === strpos($item, '.')) {
                unset($this->children[strtoupper($item)]);

                return;
            }
            // If there was a dot, we need to ask select() to help us out and
            // then we just call remove recursively.
            foreach ($this->select($item) as $child) {
                $this->remove($child);
            }
        } else {
            foreach ($this->select($item->name) as $k => $child) {
                if ($child === $item) {
                    unset($this->children[$item->name][$k]);

                    return;
                }
            }

            throw new \InvalidArgumentException('The item you passed to remove() was not a child of this component');
        }
    }

    /**
     * Returns a flat list of all the properties and components in this
     * component.
     *
     * @return array
     */
    public function children()
    {
        $result = [];
        foreach ($this->children as $childGroup) {
            $result = array_merge($result, $childGroup);
        }

        return $result;
    }

    /**
     * This method only returns a list of sub-components. Properties are
     * ignored.
     *
     * @return array
     */
    public function getComponents()
    {
        $result = [];

        foreach ($this->children as $childGroup) {
            foreach ($childGroup as $child) {
                if ($child instanceof self) {
                    $result[] = $child;
                }
            }
        }

        return $result;
    }

    /**
     * Returns an array with elements that match the specified name.
     *
     * This function is also aware of MIME-Directory groups (as they appear in
     * vcards). This means that if a property is grouped as "HOME.EMAIL", it
     * will also be returned when searching for just "EMAIL". If you want to
     * search for a property in a specific group, you can select on the entire
     * string ("HOME.EMAIL"). If you want to search on a specific property that
     * has not been assigned a group, specify ".EMAIL".
     *
     * @param string $name
     *
     * @return array
     */
    public function select($name)
    {
        $group = null;
        $name = strtoupper($name);
        if (false !== strpos($name, '.')) {
            list($group, $name) = explode('.', $name, 2);
        }
        if ('' === $name) {
            $name = null;
        }

        if (!is_null($name)) {
            $result = isset($this->children[$name]) ? $this->children[$name] : [];

            if (is_null($group)) {
                return $result;
            } else {
                // If we have a group filter as well, we need to narrow it down
                // more.
                return array_filter(
                    $result,
                    function ($child) use ($group) {
                        return $child instanceof Property && (null !== $child->group ? strtoupper($child->group) : '') === $group;
                    }
                );
            }
        }

        // If we got to this point, it means there was no 'name' specified for
        // searching, implying that this is a group-only search.
        $result = [];
        foreach ($this->children as $childGroup) {
            foreach ($childGroup as $child) {
                if ($child instanceof Property && (null !== $child->group ? strtoupper($child->group) : '') === $group) {
                    $result[] = $child;
                }
            }
        }

        return $result;
    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize()
    {
        $str = 'BEGIN:'.$this->name."\r\n";

        /**
         * Gives a component a 'score' for sorting purposes.
         *
         * This is solely used by the childrenSort method.
         *
         * A higher score means the item will be lower in the list.
         * To avoid score collisions, each "score category" has a reasonable
         * space to accommodate elements. The $key is added to the $score to
         * preserve the original relative order of elements.
         *
         * @param int   $key
         * @param array $array
         *
         * @return int
         */
        $sortScore = function ($key, $array) {
            if ($array[$key] instanceof Component) {
                // We want to encode VTIMEZONE first, this is a personal
                // preference.
                if ('VTIMEZONE' === $array[$key]->name) {
                    $score = 300000000;

                    return $score + $key;
                } else {
                    $score = 400000000;

                    return $score + $key;
                }
            } else {
                // Properties get encoded first
                // VCARD version 4.0 wants the VERSION property to appear first
                if ($array[$key] instanceof Property) {
                    if ('VERSION' === $array[$key]->name) {
                        $score = 100000000;

                        return $score + $key;
                    } else {
                        // All other properties
                        $score = 200000000;

                        return $score + $key;
                    }
                }
            }
        };

        $children = $this->children();
        $tmp = $children;
        uksort(
            $children,
            function ($a, $b) use ($sortScore, $tmp) {
                $sA = $sortScore($a, $tmp);
                $sB = $sortScore($b, $tmp);

                return $sA - $sB;
            }
        );

        foreach ($children as $child) {
            $str .= $child->serialize();
        }
        $str .= 'END:'.$this->name."\r\n";

        return $str;
    }

    /**
     * This method returns an array, with the representation as it should be
     * encoded in JSON. This is used to create jCard or jCal documents.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $components = [];
        $properties = [];

        foreach ($this->children as $childGroup) {
            foreach ($childGroup as $child) {
                if ($child instanceof self) {
                    $components[] = $child->jsonSerialize();
                } else {
                    $properties[] = $child->jsonSerialize();
                }
            }
        }

        return [
            strtolower($this->name),
            $properties,
            $components,
        ];
    }

    /**
     * This method serializes the data into XML. This is used to create xCard or
     * xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    public function xmlSerialize(Xml\Writer $writer): void
    {
        $components = [];
        $properties = [];

        foreach ($this->children as $childGroup) {
            foreach ($childGroup as $child) {
                if ($child instanceof self) {
                    $components[] = $child;
                } else {
                    $properties[] = $child;
                }
            }
        }

        $writer->startElement(strtolower($this->name));

        if (!empty($properties)) {
            $writer->startElement('properties');

            foreach ($properties as $property) {
                $property->xmlSerialize($writer);
            }

            $writer->endElement();
        }

        if (!empty($components)) {
            $writer->startElement('components');

            foreach ($components as $component) {
                $component->xmlSerialize($writer);
            }

            $writer->endElement();
        }

        $writer->endElement();
    }

    /**
     * This method should return a list of default property values.
     *
     * @return array
     */
    protected function getDefaults()
    {
        return [];
    }

    /* Magic property accessors {{{ */

    /**
     * Using 'get' you will either get a property or component.
     *
     * If there were no child-elements found with the specified name,
     * null is returned.
     *
     * To use this, this may look something like this:
     *
     * $event = $calendar->VEVENT;
     *
     * @param string $name
     *
     * @return Property|null
     */
    public function __get($name)
    {
        if ('children' === $name) {
            throw new \RuntimeException('Starting sabre/vobject 4.0 the children property is now protected. You should use the children() method instead');
        }

        $matches = $this->select($name);
        if (0 === count($matches)) {
            return;
        } else {
            $firstMatch = current($matches);
            /* @var $firstMatch Property */
            $firstMatch->setIterator(new ElementList(array_values($matches)));

            return $firstMatch;
        }
    }

    /**
     * This method checks if a sub-element with the specified name exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        $matches = $this->select($name);

        return count($matches) > 0;
    }

    /**
     * Using the setter method you can add properties or subcomponents.
     *
     * You can either pass a Component, Property
     * object, or a string to automatically create a Property.
     *
     * If the item already exists, it will be removed. If you want to add
     * a new item with the same name, always use the add() method.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $name = strtoupper($name);
        $this->remove($name);
        if ($value instanceof self || $value instanceof Property) {
            $this->add($value);
        } else {
            $this->add($name, $value);
        }
    }

    /**
     * Removes all properties and components within this component with the
     * specified name.
     *
     * @param string $name
     */
    public function __unset($name)
    {
        $this->remove($name);
    }

    /* }}} */

    /**
     * This method is automatically called when the object is cloned.
     * Specifically, this will ensure all child elements are also cloned.
     */
    public function __clone()
    {
        foreach ($this->children as $childName => $childGroup) {
            foreach ($childGroup as $key => $child) {
                $clonedChild = clone $child;
                $clonedChild->parent = $this;
                $clonedChild->root = $this->root;
                $this->children[$childName][$key] = $clonedChild;
            }
        }
    }

    /**
     * A simple list of validation rules.
     *
     * This is simply a list of properties, and how many times they either
     * must or must not appear.
     *
     * Possible values per property:
     *   * 0 - Must not appear.
     *   * 1 - Must appear exactly once.
     *   * + - Must appear at least once.
     *   * * - Can appear any number of times.
     *   * ? - May appear, but not more than once.
     *
     * It is also possible to specify defaults and severity levels for
     * violating the rule.
     *
     * See the VEVENT implementation for getValidationRules for a more complex
     * example.
     *
     * @var array
     */
    public function getValidationRules()
    {
        return [];
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   Node::REPAIR - May attempt to automatically repair the problem.
     *   Node::PROFILE_CARDDAV - Validate the vCard for CardDAV purposes.
     *   Node::PROFILE_CALDAV - Validate the iCalendar for CalDAV purposes.
     *
     * This method returns an array with detected problems.
     * Every element has the following properties:
     *
     *  * level - problem level.
     *  * message - A human-readable string describing the issue.
     *  * node - A reference to the problematic node.
     *
     * The level means:
     *   1 - The issue was repaired (only happens if REPAIR was turned on).
     *   2 - A warning.
     *   3 - An error.
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        $rules = $this->getValidationRules();
        $defaults = $this->getDefaults();

        $propertyCounters = [];

        $messages = [];

        foreach ($this->children() as $child) {
            $name = strtoupper($child->name);
            if (!isset($propertyCounters[$name])) {
                $propertyCounters[$name] = 1;
            } else {
                ++$propertyCounters[$name];
            }
            $messages = array_merge($messages, $child->validate($options));
        }

        foreach ($rules as $propName => $rule) {
            switch ($rule) {
                case '0':
                    if (isset($propertyCounters[$propName])) {
                        $messages[] = [
                            'level' => 3,
                            'message' => $propName.' MUST NOT appear in a '.$this->name.' component',
                            'node' => $this,
                        ];
                    }
                    break;
                case '1':
                    if (!isset($propertyCounters[$propName]) || 1 !== $propertyCounters[$propName]) {
                        $repaired = false;
                        if ($options & self::REPAIR && isset($defaults[$propName])) {
                            $this->add($propName, $defaults[$propName]);
                            $repaired = true;
                        }
                        $messages[] = [
                            'level' => $repaired ? 1 : 3,
                            'message' => $propName.' MUST appear exactly once in a '.$this->name.' component',
                            'node' => $this,
                        ];
                    }
                    break;
                case '+':
                    if (!isset($propertyCounters[$propName]) || $propertyCounters[$propName] < 1) {
                        $messages[] = [
                            'level' => 3,
                            'message' => $propName.' MUST appear at least once in a '.$this->name.' component',
                            'node' => $this,
                        ];
                    }
                    break;
                case '*':
                    break;
                case '?':
                    if (isset($propertyCounters[$propName]) && $propertyCounters[$propName] > 1) {
                        $level = 3;

                        // We try to repair the same property appearing multiple times with the exact same value
                        // by removing the duplicates and keeping only one property
                        if ($options & self::REPAIR) {
                            $properties = array_unique($this->select($propName), SORT_REGULAR);

                            if (1 === count($properties)) {
                                $this->remove($propName);
                                $this->add($properties[0]);

                                $level = 1;
                            }
                        }

                        $messages[] = [
                            'level' => $level,
                            'message' => $propName.' MUST NOT appear more than once in a '.$this->name.' component',
                            'node' => $this,
                        ];
                    }
                    break;
            }
        }

        return $messages;
    }

    /**
     * Call this method on a document if you're done using it.
     *
     * It's intended to remove all circular references, so PHP can easily clean
     * it up.
     */
    public function destroy()
    {
        parent::destroy();
        foreach ($this->children as $childGroup) {
            foreach ($childGroup as $child) {
                $child->destroy();
            }
        }
        $this->children = [];
    }
}

namespace Sabre\VObject;

use Sabre\Xml;

/**
 * Property.
 *
 * A property is always in a KEY:VALUE structure, and may optionally contain
 * parameters.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
abstract class Property extends Node
{
    /**
     * Property name.
     *
     * This will contain a string such as DTSTART, SUMMARY, FN.
     *
     * @var string
     */
    public $name;

    /**
     * Property group.
     *
     * This is only used in vcards
     *
     * @var string|null
     */
    public $group;

    /**
     * List of parameters.
     *
     * @var array
     */
    public $parameters = [];

    /**
     * Current value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string
     */
    public $delimiter = ';';

    /**
     * The line number in the original iCalendar / vCard file
     *   that corresponds with the current node
     *   if the node was read from a file.
     */
    public $lineIndex;

    /**
     * The line string from the original iCalendar / vCard file
     *   that corresponds with the current node
     *   if the node was read from a file.
     */
    public $lineString;

    /**
     * Creates the generic property.
     *
     * Parameters must be specified in key=>value syntax.
     *
     * @param Component         $root       The root document
     * @param string            $name
     * @param string|array|null $value
     * @param array             $parameters List of parameters
     * @param string            $group      The vcard property group
     */
    public function __construct(Component $root, $name, $value = null, array $parameters = [], $group = null, ?int $lineIndex = null, ?string $lineString = null)
    {
        $this->name = $name;
        $this->group = $group;

        $this->root = $root;

        foreach ($parameters as $k => $v) {
            $this->add($k, $v);
        }

        if (!is_null($value)) {
            $this->setValue($value);
        }

        if (!is_null($lineIndex)) {
            $this->lineIndex = $lineIndex;
        }

        if (!is_null($lineString)) {
            $this->lineString = $lineString;
        }
    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the current value.
     *
     * This method will always return a singular value. If this was a
     * multi-value object, some decision will be made first on how to represent
     * it as a string.
     *
     * To get the correct multi-value version, use getParts.
     *
     * @return string
     */
    public function getValue()
    {
        if (is_array($this->value)) {
            if (0 == count($this->value)) {
                return;
            } elseif (1 === count($this->value)) {
                return $this->value[0];
            } else {
                return $this->getRawMimeDirValue();
            }
        } else {
            return $this->value;
        }
    }

    /**
     * Sets a multi-valued property.
     */
    public function setParts(array $parts)
    {
        $this->value = $parts;
    }

    /**
     * Returns a multi-valued property.
     *
     * This method always returns an array, if there was only a single value,
     * it will still be wrapped in an array.
     *
     * @return array
     */
    public function getParts()
    {
        if (is_null($this->value)) {
            return [];
        } elseif (is_array($this->value)) {
            return $this->value;
        } else {
            return [$this->value];
        }
    }

    /**
     * Adds a new parameter.
     *
     * If a parameter with same name already existed, the values will be
     * combined.
     * If nameless parameter is added, we try to guess its name.
     *
     * @param string            $name
     * @param string|array|null $value
     */
    public function add($name, $value = null)
    {
        $noName = false;
        if (null === $name) {
            $name = Parameter::guessParameterNameByValue($value);
            $noName = true;
        }

        if (isset($this->parameters[strtoupper($name)])) {
            $this->parameters[strtoupper($name)]->addValue($value);
        } else {
            $param = new Parameter($this->root, $name, $value);
            $param->noName = $noName;
            $this->parameters[$param->name] = $param;
        }
    }

    /**
     * Returns an iterable list of children.
     *
     * @return array
     */
    public function parameters()
    {
        return $this->parameters;
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    abstract public function getValueType();

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    abstract public function setRawMimeDirValue($val);

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    abstract public function getRawMimeDirValue();

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize()
    {
        $str = $this->name;
        if ($this->group) {
            $str = $this->group.'.'.$this->name;
        }

        foreach ($this->parameters() as $param) {
            $str .= ';'.$param->serialize();
        }

        $str .= ':'.$this->getRawMimeDirValue();

        $str = \preg_replace(
            '/(
                (?:^.)?         # 1 additional byte in first line because of missing single space (see next line)
                .{1,74}         # max 75 bytes per line (1 byte is used for a single space added after every CRLF)
                (?![\x80-\xbf]) # prevent splitting multibyte characters
            )/x',
            "$1\r\n ",
            $str
        );

        // remove single space after last CRLF
        return \substr($str, 0, -1);
    }

    /**
     * Returns the value, in the format it should be encoded for JSON.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        return $this->getParts();
    }

    /**
     * Sets the JSON value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     */
    public function setJsonValue(array $value)
    {
        if (1 === count($value)) {
            $this->setValue(reset($value));
        } else {
            $this->setValue($value);
        }
    }

    /**
     * This method returns an array, with the representation as it should be
     * encoded in JSON. This is used to create jCard or jCal documents.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $parameters = [];

        foreach ($this->parameters as $parameter) {
            if ('VALUE' === $parameter->name) {
                continue;
            }
            $parameters[strtolower($parameter->name)] = $parameter->jsonSerialize();
        }
        // In jCard, we need to encode the property-group as a separate 'group'
        // parameter.
        if ($this->group) {
            $parameters['group'] = $this->group;
        }

        return array_merge(
            [
                strtolower($this->name),
                (object) $parameters,
                strtolower($this->getValueType()),
            ],
            $this->getJsonValue()
        );
    }

    /**
     * Hydrate data from a XML subtree, as it would appear in a xCard or xCal
     * object.
     */
    public function setXmlValue(array $value)
    {
        $this->setJsonValue($value);
    }

    /**
     * This method serializes the data into XML. This is used to create xCard or
     * xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    public function xmlSerialize(Xml\Writer $writer): void
    {
        $parameters = [];

        foreach ($this->parameters as $parameter) {
            if ('VALUE' === $parameter->name) {
                continue;
            }

            $parameters[] = $parameter;
        }

        $writer->startElement(strtolower($this->name));

        if (!empty($parameters)) {
            $writer->startElement('parameters');

            foreach ($parameters as $parameter) {
                $writer->startElement(strtolower($parameter->name));
                $writer->write($parameter);
                $writer->endElement();
            }

            $writer->endElement();
        }

        $this->xmlSerializeValue($writer);
        $writer->endElement();
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    protected function xmlSerializeValue(Xml\Writer $writer)
    {
        $valueType = strtolower($this->getValueType());

        foreach ($this->getJsonValue() as $values) {
            foreach ((array) $values as $value) {
                $writer->writeElement($valueType, $value);
            }
        }
    }

    /**
     * Called when this object is being cast to a string.
     *
     * If the property only had a single value, you will get just that. In the
     * case the property had multiple values, the contents will be escaped and
     * combined with ,.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }

    /* ArrayAccess interface {{{ */

    /**
     * Checks if an array element exists.
     *
     * @param mixed $name
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        if (is_int($name)) {
            return parent::offsetExists($name);
        }

        $name = strtoupper($name);

        foreach ($this->parameters as $parameter) {
            if ($parameter->name == $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a parameter.
     *
     * If the parameter does not exist, null is returned.
     *
     * @param string $name
     *
     * @return Node
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        if (is_int($name)) {
            return parent::offsetGet($name);
        }
        $name = strtoupper($name);

        if (!isset($this->parameters[$name])) {
            return;
        }

        return $this->parameters[$name];
    }

    /**
     * Creates a new parameter.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        if (is_int($name)) {
            parent::offsetSet($name, $value);
            // @codeCoverageIgnoreStart
            // This will never be reached, because an exception is always
            // thrown.
            return;
            // @codeCoverageIgnoreEnd
        }

        $param = new Parameter($this->root, $name, $value);
        $this->parameters[$param->name] = $param;
    }

    /**
     * Removes one or more parameters with the specified name.
     *
     * @param string $name
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        if (is_int($name)) {
            parent::offsetUnset($name);
            // @codeCoverageIgnoreStart
            // This will never be reached, because an exception is always
            // thrown.
            return;
            // @codeCoverageIgnoreEnd
        }

        unset($this->parameters[strtoupper($name)]);
    }

    /* }}} */

    /**
     * This method is automatically called when the object is cloned.
     * Specifically, this will ensure all child elements are also cloned.
     */
    public function __clone()
    {
        foreach ($this->parameters as $key => $child) {
            $this->parameters[$key] = clone $child;
            $this->parameters[$key]->parent = $this;
        }
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   - Node::REPAIR - If something is broken, and automatic repair may
     *                    be attempted.
     *
     * An array is returned with warnings.
     *
     * Every item in the array has the following properties:
     *    * level - (number between 1 and 3 with severity information)
     *    * message - (human readable message)
     *    * node - (reference to the offending node)
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        $warnings = [];

        // Checking if our value is UTF-8
        if (!StringUtil::isUTF8($this->getRawMimeDirValue())) {
            $oldValue = $this->getRawMimeDirValue();
            $level = 3;
            if ($options & self::REPAIR) {
                $newValue = StringUtil::convertToUTF8($oldValue);
                if (true || StringUtil::isUTF8($newValue)) {
                    $this->setRawMimeDirValue($newValue);
                    $level = 1;
                }
            }

            if (preg_match('%([\x00-\x08\x0B-\x0C\x0E-\x1F\x7F])%', $oldValue, $matches)) {
                $message = 'Property contained a control character (0x'.bin2hex($matches[1]).')';
            } else {
                $message = 'Property is not valid UTF-8! '.$oldValue;
            }

            $warnings[] = [
                'level' => $level,
                'message' => $message,
                'node' => $this,
            ];
        }

        // Checking if the propertyname does not contain any invalid bytes.
        if (!preg_match('/^([A-Z0-9-]+)$/', $this->name)) {
            $warnings[] = [
                'level' => $options & self::REPAIR ? 1 : 3,
                'message' => 'The propertyname: '.$this->name.' contains invalid characters. Only A-Z, 0-9 and - are allowed',
                'node' => $this,
            ];
            if ($options & self::REPAIR) {
                // Uppercasing and converting underscores to dashes.
                $this->name = strtoupper(
                    str_replace('_', '-', $this->name)
                );
                // Removing every other invalid character
                $this->name = preg_replace('/([^A-Z0-9-])/u', '', $this->name);
            }
        }

        if ($encoding = $this->offsetGet('ENCODING')) {
            if (Document::VCARD40 === $this->root->getDocumentType()) {
                $warnings[] = [
                    'level' => 3,
                    'message' => 'ENCODING parameter is not valid in vCard 4.',
                    'node' => $this,
                ];
            } else {
                $encoding = (string) $encoding;

                $allowedEncoding = [];

                switch ($this->root->getDocumentType()) {
                    case Document::ICALENDAR20:
                        $allowedEncoding = ['8BIT', 'BASE64'];
                        break;
                    case Document::VCARD21:
                        $allowedEncoding = ['QUOTED-PRINTABLE', 'BASE64', '8BIT'];
                        break;
                    case Document::VCARD30:
                        $allowedEncoding = ['B'];
                        //Repair vCard30 that use BASE64 encoding
                        if ($options & self::REPAIR) {
                            if ('BASE64' === strtoupper($encoding)) {
                                $encoding = 'B';
                                $this['ENCODING'] = $encoding;
                                $warnings[] = [
                                    'level' => 1,
                                    'message' => 'ENCODING=BASE64 has been transformed to ENCODING=B.',
                                    'node' => $this,
                                ];
                            }
                        }
                        break;
                }
                if ($allowedEncoding && !in_array(strtoupper($encoding), $allowedEncoding)) {
                    $warnings[] = [
                        'level' => 3,
                        'message' => 'ENCODING='.strtoupper($encoding).' is not valid for this document type.',
                        'node' => $this,
                    ];
                }
            }
        }

        // Validating inner parameters
        foreach ($this->parameters as $param) {
            $warnings = array_merge($warnings, $param->validate($options));
        }

        return $warnings;
    }

    /**
     * Call this method on a document if you're done using it.
     *
     * It's intended to remove all circular references, so PHP can easily clean
     * it up.
     */
    public function destroy()
    {
        parent::destroy();
        foreach ($this->parameters as $param) {
            $param->destroy();
        }
        $this->parameters = [];
    }
}

namespace Sabre\VObject;

use ArrayIterator;
use Sabre\Xml;

/**
 * VObject Parameter.
 *
 * This class represents a parameter. A parameter is always tied to a property.
 * In the case of:
 *   DTSTART;VALUE=DATE:20101108
 * VALUE=DATE would be the parameter name and value.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Parameter extends Node
{
    /**
     * Parameter name.
     *
     * @var string
     */
    public $name;

    /**
     * vCard 2.1 allows parameters to be encoded without a name.
     *
     * We can deduce the parameter name based on its value.
     *
     * @var bool
     */
    public $noName = false;

    /**
     * Parameter value.
     *
     * @var string
     */
    protected $value;

    /**
     * Sets up the object.
     *
     * It's recommended to use the create:: factory method instead.
     *
     * @param string $name
     * @param string $value
     */
    public function __construct(Document $root, $name, $value = null)
    {
        $this->root = $root;
        if (is_null($name)) {
            $this->noName = true;
            $this->name = static::guessParameterNameByValue($value);
        } else {
            $this->name = strtoupper($name);
        }

        // If guessParameterNameByValue() returns an empty string
        // above, we're actually dealing with a parameter that has no value.
        // In that case we have to move the value to the name.
        if ('' === $this->name) {
            $this->noName = false;
            $this->name = strtoupper($value);
        } else {
            $this->setValue($value);
        }
    }

    /**
     * Try to guess property name by value, can be used for vCard 2.1 nameless parameters.
     *
     * Figuring out what the name should have been. Note that a ton of
     * these are rather silly in 2014 and would probably rarely be
     * used, but we like to be complete.
     *
     * @param string $value
     *
     * @return string
     */
    public static function guessParameterNameByValue($value)
    {
        switch (strtoupper($value)) {
            // Encodings
            case '7-BIT':
            case 'QUOTED-PRINTABLE':
            case 'BASE64':
                $name = 'ENCODING';
                break;

            // Common types
            case 'WORK':
            case 'HOME':
            case 'PREF':
            // Delivery Label Type
            case 'DOM':
            case 'INTL':
            case 'POSTAL':
            case 'PARCEL':
            // Telephone types
            case 'VOICE':
            case 'FAX':
            case 'MSG':
            case 'CELL':
            case 'PAGER':
            case 'BBS':
            case 'MODEM':
            case 'CAR':
            case 'ISDN':
            case 'VIDEO':
            // EMAIL types (lol)
            case 'AOL':
            case 'APPLELINK':
            case 'ATTMAIL':
            case 'CIS':
            case 'EWORLD':
            case 'INTERNET':
            case 'IBMMAIL':
            case 'MCIMAIL':
            case 'POWERSHARE':
            case 'PRODIGY':
            case 'TLX':
            case 'X400':
            // Photo / Logo format types
            case 'GIF':
            case 'CGM':
            case 'WMF':
            case 'BMP':
            case 'DIB':
            case 'PICT':
            case 'TIFF':
            case 'PDF':
            case 'PS':
            case 'JPEG':
            case 'MPEG':
            case 'MPEG2':
            case 'AVI':
            case 'QTIME':
            // Sound Digital Audio Type
            case 'WAVE':
            case 'PCM':
            case 'AIFF':
            // Key types
            case 'X509':
            case 'PGP':
                $name = 'TYPE';
                break;

            // Value types
            case 'INLINE':
            case 'URL':
            case 'CONTENT-ID':
            case 'CID':
                $name = 'VALUE';
                break;

            default:
                $name = '';
        }

        return $name;
    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the current value.
     *
     * This method will always return a string, or null. If there were multiple
     * values, it will automatically concatenate them (separated by comma).
     *
     * @return string|null
     */
    public function getValue()
    {
        if (is_array($this->value)) {
            return implode(',', $this->value);
        } else {
            return $this->value;
        }
    }

    /**
     * Sets multiple values for this parameter.
     */
    public function setParts(array $value)
    {
        $this->value = $value;
    }

    /**
     * Returns all values for this parameter.
     *
     * If there were no values, an empty array will be returned.
     *
     * @return array
     */
    public function getParts()
    {
        if (is_array($this->value)) {
            return $this->value;
        } elseif (is_null($this->value)) {
            return [];
        } else {
            return [$this->value];
        }
    }

    /**
     * Adds a value to this parameter.
     *
     * If the argument is specified as an array, all items will be added to the
     * parameter value list.
     *
     * @param string|array $part
     */
    public function addValue($part)
    {
        if (is_null($this->value)) {
            $this->value = $part;
        } else {
            $this->value = array_merge((array) $this->value, (array) $part);
        }
    }

    /**
     * Checks if this parameter contains the specified value.
     *
     * This is a case-insensitive match. It makes sense to call this for for
     * instance the TYPE parameter, to see if it contains a keyword such as
     * 'WORK' or 'FAX'.
     *
     * @param string $value
     *
     * @return bool
     */
    public function has($value)
    {
        return in_array(
            strtolower($value),
            array_map('strtolower', (array) $this->value)
        );
    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize()
    {
        $value = $this->getParts();

        if (0 === count($value)) {
            return $this->name.'=';
        }

        if (Document::VCARD21 === $this->root->getDocumentType() && $this->noName) {
            return implode(';', $value);
        }

        return $this->name.'='.array_reduce(
            $value,
            function ($out, $item) {
                if (!is_null($out)) {
                    $out .= ',';
                }

                // If there's no special characters in the string, we'll use the simple
                // format.
                //
                // The list of special characters is defined as:
                //
                // Any character except CONTROL, DQUOTE, ";", ":", ","
                //
                // by the iCalendar spec:
                // https://tools.ietf.org/html/rfc5545#section-3.1
                //
                // And we add ^ to that because of:
                // https://tools.ietf.org/html/rfc6868
                //
                // But we've found that iCal (7.0, shipped with OSX 10.9)
                // severely trips on + characters not being quoted, so we
                // added + as well.
                if (!preg_match('#(?: [\n":;\^,\+] )#x', $item)) {
                    return $out.$item;
                } else {
                    // Enclosing in double-quotes, and using RFC6868 for encoding any
                    // special characters
                    $out .= '"'.strtr(
                        $item,
                        [
                            '^' => '^^',
                            "\n" => '^n',
                            '"' => '^\'',
                        ]
                    ).'"';

                    return $out;
                }
            }
        );
    }

    /**
     * This method returns an array, with the representation as it should be
     * encoded in JSON. This is used to create jCard or jCal documents.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->value;
    }

    /**
     * This method serializes the data into XML. This is used to create xCard or
     * xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    public function xmlSerialize(Xml\Writer $writer): void
    {
        foreach (explode(',', $this->value) as $value) {
            $writer->writeElement('text', $value);
        }
    }

    /**
     * Called when this object is being cast to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }

    /**
     * Returns the iterator for this object.
     *
     * @return ElementList
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        if (!is_null($this->iterator)) {
            return $this->iterator;
        }

        return $this->iterator = new ArrayIterator((array) $this->value);
    }
}

namespace Sabre\VObject;

/**
 * Document.
 *
 * A document is just like a component, except that it's also the top level
 * element.
 *
 * Both a VCALENDAR and a VCARD are considered documents.
 *
 * This class also provides a registry for document types.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
abstract class Document extends Component
{
    /**
     * Unknown document type.
     */
    const UNKNOWN = 1;

    /**
     * vCalendar 1.0.
     */
    const VCALENDAR10 = 2;

    /**
     * iCalendar 2.0.
     */
    const ICALENDAR20 = 3;

    /**
     * vCard 2.1.
     */
    const VCARD21 = 4;

    /**
     * vCard 3.0.
     */
    const VCARD30 = 5;

    /**
     * vCard 4.0.
     */
    const VCARD40 = 6;

    /**
     * The default name for this component.
     *
     * This should be 'VCALENDAR' or 'VCARD'.
     *
     * @var string
     */
    public static $defaultName;

    /**
     * List of properties, and which classes they map to.
     *
     * @var array
     */
    public static $propertyMap = [];

    /**
     * List of components, along with which classes they map to.
     *
     * @var array
     */
    public static $componentMap = [];

    /**
     * List of value-types, and which classes they map to.
     *
     * @var array
     */
    public static $valueMap = [];

    /**
     * Creates a new document.
     *
     * We're changing the default behavior slightly here. First, we don't want
     * to have to specify a name (we already know it), and we want to allow
     * children to be specified in the first argument.
     *
     * But, the default behavior also works.
     *
     * So the two sigs:
     *
     * new Document(array $children = [], $defaults = true);
     * new Document(string $name, array $children = [], $defaults = true)
     */
    public function __construct()
    {
        $args = func_get_args();
        $name = static::$defaultName;
        if (0 === count($args) || is_array($args[0])) {
            $children = isset($args[0]) ? $args[0] : [];
            $defaults = isset($args[1]) ? $args[1] : true;
        } else {
            $name = $args[0];
            $children = isset($args[1]) ? $args[1] : [];
            $defaults = isset($args[2]) ? $args[2] : true;
        }
        parent::__construct($this, $name, $children, $defaults);
    }

    /**
     * Returns the current document type.
     *
     * @return int
     */
    public function getDocumentType()
    {
        return self::UNKNOWN;
    }

    /**
     * Creates a new component or property.
     *
     * If it's a known component, we will automatically call createComponent.
     * otherwise, we'll assume it's a property and call createProperty instead.
     *
     * @param string $name
     * @param string $arg1,... Unlimited number of args
     *
     * @return mixed
     */
    public function create($name)
    {
        if (isset(static::$componentMap[strtoupper($name)])) {
            return call_user_func_array([$this, 'createComponent'], func_get_args());
        } else {
            return call_user_func_array([$this, 'createProperty'], func_get_args());
        }
    }

    /**
     * Creates a new component.
     *
     * This method automatically searches for the correct component class, based
     * on its name.
     *
     * You can specify the children either in key=>value syntax, in which case
     * properties will automatically be created, or you can just pass a list of
     * Component and Property object.
     *
     * By default, a set of sensible values will be added to the component. For
     * an iCalendar object, this may be something like CALSCALE:GREGORIAN. To
     * ensure that this does not happen, set $defaults to false.
     *
     * @param string $name
     * @param array  $children
     * @param bool   $defaults
     *
     * @return Component
     */
    public function createComponent($name, ?array $children = null, $defaults = true)
    {
        $name = strtoupper($name);
        $class = Component::class;

        if (isset(static::$componentMap[$name])) {
            $class = static::$componentMap[$name];
        }
        if (is_null($children)) {
            $children = [];
        }

        return new $class($this, $name, $children, $defaults);
    }

    /**
     * Factory method for creating new properties.
     *
     * This method automatically searches for the correct property class, based
     * on its name.
     *
     * You can specify the parameters either in key=>value syntax, in which case
     * parameters will automatically be created, or you can just pass a list of
     * Parameter objects.
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $parameters
     * @param string $valueType  Force a specific valuetype, such as URI or TEXT
     */
    public function createProperty($name, $value = null, ?array $parameters = null, $valueType = null, ?int $lineIndex = null, ?string $lineString = null): Property
    {
        // If there's a . in the name, it means it's prefixed by a groupname.
        if (false !== ($i = strpos($name, '.'))) {
            $group = substr($name, 0, $i);
            $name = strtoupper(substr($name, $i + 1));
        } else {
            $name = strtoupper($name);
            $group = null;
        }

        $class = null;

        // If a VALUE parameter is supplied, we have to use that
        // According to https://datatracker.ietf.org/doc/html/rfc5545#section-3.2.20
        //  If the property's value is the default value type, then this
        //  parameter need not be specified.  However, if the property's
        //  default value type is overridden by some other allowable value
        //  type, then this parameter MUST be specified.
        if (!$valueType) {
            $valueType = $parameters['VALUE'] ?? null;
        }

        if ($valueType) {
            // The valueType argument comes first to figure out the correct
            // class.
            $class = $this->getClassNameForPropertyValue($valueType);
        }

        // If the value parameter is not set or set to something we do not recognize
        // we do not attempt to interpret or parse the datass value as specified in
        // https://datatracker.ietf.org/doc/html/rfc5545#section-3.2.20
        // So when we so far did not get a class-name, we use the default for the property
        if (is_null($class)) {
            $class = $this->getClassNameForPropertyName($name);
        }

        if (is_null($parameters)) {
            $parameters = [];
        }

        return new $class($this, $name, $value, $parameters, $group, $lineIndex, $lineString);
    }

    /**
     * This method returns a full class-name for a value parameter.
     *
     * For instance, DTSTART may have VALUE=DATE. In that case we will look in
     * our valueMap table and return the appropriate class name.
     *
     * This method returns null if we don't have a specialized class.
     *
     * @param string $valueParam
     *
     * @return string|null
     */
    public function getClassNameForPropertyValue($valueParam)
    {
        $valueParam = strtoupper($valueParam);
        if (isset(static::$valueMap[$valueParam])) {
            return static::$valueMap[$valueParam];
        }
    }

    /**
     * Returns the default class for a property name.
     *
     * @param string $propertyName
     *
     * @return string
     */
    public function getClassNameForPropertyName($propertyName)
    {
        if (isset(static::$propertyMap[$propertyName])) {
            return static::$propertyMap[$propertyName];
        } else {
            return Property\Unknown::class;
        }
    }
}

namespace Sabre\VObject;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * DateTimeParser.
 *
 * This class is responsible for parsing the several different date and time
 * formats iCalendar and vCards have.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class DateTimeParser
{
    /**
     * Parses an iCalendar (rfc5545) formatted datetime and returns a
     * DateTimeImmutable object.
     *
     * Specifying a reference timezone is optional. It will only be used
     * if the non-UTC format is used. The argument is used as a reference, the
     * returned DateTimeImmutable object will still be in the UTC timezone.
     *
     * @param string       $dt
     * @param DateTimeZone $tz
     *
     * @return DateTimeImmutable
     */
    public static function parseDateTime($dt, ?DateTimeZone $tz = null)
    {
        // Format is YYYYMMDD + "T" + hhmmss
        $result = preg_match('/^([0-9]{4})([0-1][0-9])([0-3][0-9])T([0-2][0-9])([0-5][0-9])([0-5][0-9])([Z]?)$/', $dt, $matches);

        if (!$result) {
            throw new InvalidDataException('The supplied iCalendar datetime value is incorrect: '.$dt);
        }

        if ('Z' === $matches[7] || is_null($tz)) {
            $tz = new DateTimeZone('UTC');
        }

        try {
            $date = new DateTimeImmutable($matches[1].'-'.$matches[2].'-'.$matches[3].' '.$matches[4].':'.$matches[5].':'.$matches[6], $tz);
        } catch (\Exception $e) {
            throw new InvalidDataException('The supplied iCalendar datetime value is incorrect: '.$dt);
        }

        return $date;
    }

    /**
     * Parses an iCalendar (rfc5545) formatted date and returns a DateTimeImmutable object.
     *
     * @param string       $date
     * @param DateTimeZone $tz
     *
     * @return DateTimeImmutable
     */
    public static function parseDate($date, ?DateTimeZone $tz = null)
    {
        // Format is YYYYMMDD
        $result = preg_match('/^([0-9]{4})([0-1][0-9])([0-3][0-9])$/', $date, $matches);

        if (!$result) {
            throw new InvalidDataException('The supplied iCalendar date value is incorrect: '.$date);
        }

        if (is_null($tz)) {
            $tz = new DateTimeZone('UTC');
        }

        try {
            $date = new DateTimeImmutable($matches[1].'-'.$matches[2].'-'.$matches[3], $tz);
        } catch (\Exception $e) {
            throw new InvalidDataException('The supplied iCalendar date value is incorrect: '.$date);
        }

        return $date;
    }

    /**
     * Parses an iCalendar (RFC5545) formatted duration value.
     *
     * This method will either return a DateTimeInterval object, or a string
     * suitable for strtotime or DateTime::modify.
     *
     * @param string $duration
     * @param bool   $asString
     *
     * @return DateInterval|string
     */
    public static function parseDuration($duration, $asString = false)
    {
        $result = preg_match('/^(?<plusminus>\+|-)?P((?<week>\d+)W)?((?<day>\d+)D)?(T((?<hour>\d+)H)?((?<minute>\d+)M)?((?<second>\d+)S)?)?$/', $duration, $matches);
        if (!$result) {
            throw new InvalidDataException('The supplied iCalendar duration value is incorrect: '.$duration);
        }

        if (!$asString) {
            $invert = false;

            if (isset($matches['plusminus']) && '-' === $matches['plusminus']) {
                $invert = true;
            }

            $parts = [
                'week',
                'day',
                'hour',
                'minute',
                'second',
            ];

            foreach ($parts as $part) {
                $matches[$part] = isset($matches[$part]) && $matches[$part] ? (int) $matches[$part] : 0;
            }

            // We need to re-construct the $duration string, because weeks and
            // days are not supported by DateInterval in the same string.
            $duration = 'P';
            $days = $matches['day'];

            if ($matches['week']) {
                $days += $matches['week'] * 7;
            }

            if ($days) {
                $duration .= $days.'D';
            }

            if ($matches['minute'] || $matches['second'] || $matches['hour']) {
                $duration .= 'T';

                if ($matches['hour']) {
                    $duration .= $matches['hour'].'H';
                }

                if ($matches['minute']) {
                    $duration .= $matches['minute'].'M';
                }

                if ($matches['second']) {
                    $duration .= $matches['second'].'S';
                }
            }

            if ('P' === $duration) {
                $duration = 'PT0S';
            }

            $iv = new DateInterval($duration);

            if ($invert) {
                $iv->invert = true;
            }

            return $iv;
        }

        $parts = [
            'week',
            'day',
            'hour',
            'minute',
            'second',
        ];

        $newDur = '';

        foreach ($parts as $part) {
            if (isset($matches[$part]) && $matches[$part]) {
                $newDur .= ' '.$matches[$part].' '.$part.'s';
            }
        }

        $newDur = ('-' === $matches['plusminus'] ? '-' : '+').trim($newDur);

        if ('+' === $newDur) {
            $newDur = '+0 seconds';
        }

        return $newDur;
    }

    /**
     * Parses either a Date or DateTime, or Duration value.
     *
     * @param string              $date
     * @param DateTimeZone|string $referenceTz
     *
     * @return DateTimeImmutable|DateInterval
     */
    public static function parse($date, $referenceTz = null)
    {
        if ('P' === $date[0] || ('-' === $date[0] && 'P' === $date[1])) {
            return self::parseDuration($date);
        } elseif (8 === strlen($date)) {
            return self::parseDate($date, $referenceTz);
        } else {
            return self::parseDateTime($date, $referenceTz);
        }
    }

    /**
     * This method parses a vCard date and or time value.
     *
     * This can be used for the DATE, DATE-TIME, TIMESTAMP and
     * DATE-AND-OR-TIME value.
     *
     * This method returns an array, not a DateTime value.
     *
     * The elements in the array are in the following order:
     * year, month, date, hour, minute, second, timezone
     *
     * Almost any part of the string may be omitted. It's for example legal to
     * just specify seconds, leave out the year, etc.
     *
     * Timezone is either returned as 'Z' or as '+0800'
     *
     * For any non-specified values null is returned.
     *
     * List of date formats that are supported:
     * YYYY
     * YYYY-MM
     * YYYYMMDD
     * --MMDD
     * ---DD
     *
     * YYYY-MM-DD
     * --MM-DD
     * ---DD
     *
     * List of supported time formats:
     *
     * HH
     * HHMM
     * HHMMSS
     * -MMSS
     * --SS
     *
     * HH
     * HH:MM
     * HH:MM:SS
     * -MM:SS
     * --SS
     *
     * A full basic-format date-time string looks like :
     * 20130603T133901
     *
     * A full extended-format date-time string looks like :
     * 2013-06-03T13:39:01
     *
     * Times may be postfixed by a timezone offset. This can be either 'Z' for
     * UTC, or a string like -0500 or +1100.
     *
     * @param string $date
     *
     * @return array
     */
    public static function parseVCardDateTime($date)
    {
        $regex = '/^
            (?:  # date part
                (?:
                    (?: (?<year> [0-9]{4}) (?: -)?| --)
                    (?<month> [0-9]{2})?
                |---)
                (?<date> [0-9]{2})?
            )?
            (?:T  # time part
                (?<hour> [0-9]{2} | -)
                (?<minute> [0-9]{2} | -)?
                (?<second> [0-9]{2})?

                (?: \.[0-9]{3})? # milliseconds
                (?P<timezone> # timezone offset

                    Z | (?: \+|-)(?: [0-9]{4})

                )?

            )?
            $/x';

        if (!preg_match($regex, $date, $matches)) {
            // Attempting to parse the extended format.
            $regex = '/^
                (?: # date part
                    (?: (?<year> [0-9]{4}) - | -- )
                    (?<month> [0-9]{2}) -
                    (?<date> [0-9]{2})
                )?
                (?:T # time part

                    (?: (?<hour> [0-9]{2}) : | -)
                    (?: (?<minute> [0-9]{2}) : | -)?
                    (?<second> [0-9]{2})?

                    (?: \.[0-9]{3})? # milliseconds
                    (?P<timezone> # timezone offset

                        Z | (?: \+|-)(?: [0-9]{2}:[0-9]{2})

                    )?

                )?
                $/x';

            if (!preg_match($regex, $date, $matches)) {
                throw new InvalidDataException('Invalid vCard date-time string: '.$date);
            }
        }
        $parts = [
            'year',
            'month',
            'date',
            'hour',
            'minute',
            'second',
            'timezone',
        ];

        $result = [];
        foreach ($parts as $part) {
            if (empty($matches[$part])) {
                $result[$part] = null;
            } elseif ('-' === $matches[$part] || '--' === $matches[$part]) {
                $result[$part] = null;
            } else {
                $result[$part] = $matches[$part];
            }
        }

        return $result;
    }

    /**
     * This method parses a vCard TIME value.
     *
     * This method returns an array, not a DateTime value.
     *
     * The elements in the array are in the following order:
     * hour, minute, second, timezone
     *
     * Almost any part of the string may be omitted. It's for example legal to
     * just specify seconds, leave out the hour etc.
     *
     * Timezone is either returned as 'Z' or as '+08:00'
     *
     * For any non-specified values null is returned.
     *
     * List of supported time formats:
     *
     * HH
     * HHMM
     * HHMMSS
     * -MMSS
     * --SS
     *
     * HH
     * HH:MM
     * HH:MM:SS
     * -MM:SS
     * --SS
     *
     * A full basic-format time string looks like :
     * 133901
     *
     * A full extended-format time string looks like :
     * 13:39:01
     *
     * Times may be postfixed by a timezone offset. This can be either 'Z' for
     * UTC, or a string like -0500 or +11:00.
     *
     * @param string $date
     *
     * @return array
     */
    public static function parseVCardTime($date)
    {
        $regex = '/^
            (?<hour> [0-9]{2} | -)
            (?<minute> [0-9]{2} | -)?
            (?<second> [0-9]{2})?

            (?: \.[0-9]{3})? # milliseconds
            (?P<timezone> # timezone offset

                Z | (?: \+|-)(?: [0-9]{4})

            )?
            $/x';

        if (!preg_match($regex, $date, $matches)) {
            // Attempting to parse the extended format.
            $regex = '/^
                (?: (?<hour> [0-9]{2}) : | -)
                (?: (?<minute> [0-9]{2}) : | -)?
                (?<second> [0-9]{2})?

                (?: \.[0-9]{3})? # milliseconds
                (?P<timezone> # timezone offset

                    Z | (?: \+|-)(?: [0-9]{2}:[0-9]{2})

                )?
                $/x';

            if (!preg_match($regex, $date, $matches)) {
                throw new InvalidDataException('Invalid vCard time string: '.$date);
            }
        }
        $parts = [
            'hour',
            'minute',
            'second',
            'timezone',
        ];

        $result = [];
        foreach ($parts as $part) {
            if (empty($matches[$part])) {
                $result[$part] = null;
            } elseif ('-' === $matches[$part]) {
                $result[$part] = null;
            } else {
                $result[$part] = $matches[$part];
            }
        }

        return $result;
    }

    /**
     * This method parses a vCard date and or time value.
     *
     * This can be used for the DATE, DATE-TIME and
     * DATE-AND-OR-TIME value.
     *
     * This method returns an array, not a DateTime value.
     * The elements in the array are in the following order:
     *     year, month, date, hour, minute, second, timezone
     * Almost any part of the string may be omitted. It's for example legal to
     * just specify seconds, leave out the year, etc.
     *
     * Timezone is either returned as 'Z' or as '+0800'
     *
     * For any non-specified values null is returned.
     *
     * List of date formats that are supported:
     *     20150128
     *     2015-01
     *     --01
     *     --0128
     *     ---28
     *
     * List of supported time formats:
     *     13
     *     1353
     *     135301
     *     -53
     *     -5301
     *     --01 (unreachable, see the tests)
     *     --01Z
     *     --01+1234
     *
     * List of supported date-time formats:
     *     20150128T13
     *     --0128T13
     *     ---28T13
     *     ---28T1353
     *     ---28T135301
     *     ---28T13Z
     *     ---28T13+1234
     *
     * See the regular expressions for all the possible patterns.
     *
     * Times may be postfixed by a timezone offset. This can be either 'Z' for
     * UTC, or a string like -0500 or +1100.
     *
     * @param string $date
     *
     * @return array
     */
    public static function parseVCardDateAndOrTime($date)
    {
        // \d{8}|\d{4}-\d\d|--\d\d(\d\d)?|---\d\d
        $valueDate = '/^(?J)(?:'.
                         '(?<year>\d{4})(?<month>\d\d)(?<date>\d\d)'.
                         '|(?<year>\d{4})-(?<month>\d\d)'.
                         '|--(?<month>\d\d)(?<date>\d\d)?'.
                         '|---(?<date>\d\d)'.
                         ')$/';

        // (\d\d(\d\d(\d\d)?)?|-\d\d(\d\d)?|--\d\d)(Z|[+\-]\d\d(\d\d)?)?
        $valueTime = '/^(?J)(?:'.
                         '((?<hour>\d\d)((?<minute>\d\d)(?<second>\d\d)?)?'.
                         '|-(?<minute>\d\d)(?<second>\d\d)?'.
                         '|--(?<second>\d\d))'.
                         '(?<timezone>(Z|[+\-]\d\d(\d\d)?))?'.
                         ')$/';

        // (\d{8}|--\d{4}|---\d\d)T\d\d(\d\d(\d\d)?)?(Z|[+\-]\d\d(\d\d?)?
        $valueDateTime = '/^(?:'.
                         '((?<year0>\d{4})(?<month0>\d\d)(?<date0>\d\d)'.
                         '|--(?<month1>\d\d)(?<date1>\d\d)'.
                         '|---(?<date2>\d\d))'.
                         'T'.
                         '(?<hour>\d\d)((?<minute>\d\d)(?<second>\d\d)?)?'.
                         '(?<timezone>(Z|[+\-]\d\d(\d\d?)))?'.
                         ')$/';

        // date-and-or-time is date | date-time | time
        // in this strict order.

        if (0 === preg_match($valueDate, $date, $matches)
            && 0 === preg_match($valueDateTime, $date, $matches)
            && 0 === preg_match($valueTime, $date, $matches)) {
            throw new InvalidDataException('Invalid vCard date-time string: '.$date);
        }

        $parts = [
            'year' => null,
            'month' => null,
            'date' => null,
            'hour' => null,
            'minute' => null,
            'second' => null,
            'timezone' => null,
        ];

        // The $valueDateTime expression has a bug with (?J) so we simulate it.
        $parts['date0'] = &$parts['date'];
        $parts['date1'] = &$parts['date'];
        $parts['date2'] = &$parts['date'];
        $parts['month0'] = &$parts['month'];
        $parts['month1'] = &$parts['month'];
        $parts['year0'] = &$parts['year'];

        foreach ($parts as $part => &$value) {
            if (!empty($matches[$part])) {
                $value = $matches[$part];
            }
        }

        unset($parts['date0']);
        unset($parts['date1']);
        unset($parts['date2']);
        unset($parts['month0']);
        unset($parts['month1']);
        unset($parts['year0']);

        return $parts;
    }
}

namespace Sabre\VObject\Property;

use Sabre\VObject\Component;
use Sabre\VObject\Document;
use Sabre\VObject\Parser\MimeDir;
use Sabre\VObject\Property;
use Sabre\Xml;

/**
 * Text property.
 *
 * This object represents TEXT values.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Text extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * List of properties that are considered 'structured'.
     *
     * @var array
     */
    protected $structuredValues = [
        // vCard
        'N',
        'ADR',
        'ORG',
        'GENDER',
        'CLIENTPIDMAP',

        // iCalendar
        'REQUEST-STATUS',
    ];

    /**
     * Some text components have a minimum number of components.
     *
     * N must for instance be represented as 5 components, separated by ;, even
     * if the last few components are unused.
     *
     * @var array
     */
    protected $minimumPropertyValues = [
        'N' => 5,
        'ADR' => 7,
    ];

    /**
     * Creates the property.
     *
     * You can specify the parameters either in key=>value syntax, in which case
     * parameters will automatically be created, or you can just pass a list of
     * Parameter objects.
     *
     * @param Component         $root       The root document
     * @param string            $name
     * @param string|array|null $value
     * @param array             $parameters List of parameters
     * @param string            $group      The vcard property group
     */
    public function __construct(Component $root, $name, $value = null, array $parameters = [], $group = null)
    {
        // There's two types of multi-valued text properties:
        // 1. multivalue properties.
        // 2. structured value properties
        //
        // The former is always separated by a comma, the latter by semi-colon.
        if (in_array($name, $this->structuredValues)) {
            $this->delimiter = ';';
        }

        parent::__construct($root, $name, $value, $parameters, $group);
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        $this->setValue(MimeDir::unescapeValue($val, $this->delimiter));
    }

    /**
     * Sets the value as a quoted-printable encoded string.
     *
     * @param string $val
     */
    public function setQuotedPrintableValue($val)
    {
        $val = quoted_printable_decode($val);

        // Quoted printable only appears in vCard 2.1, and the only character
        // that may be escaped there is ;. So we are simply splitting on just
        // that.
        //
        // We also don't have to unescape \\, so all we need to look for is a ;
        // that's not preceded with a \.
        $regex = '# (?<!\\\\) ; #x';
        $matches = preg_split($regex, $val);
        $this->setValue($matches);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        $val = $this->getParts();

        if (isset($this->minimumPropertyValues[$this->name])) {
            $val = array_pad($val, $this->minimumPropertyValues[$this->name], '');
        }

        foreach ($val as &$item) {
            if (!is_array($item)) {
                $item = [$item];
            }

            foreach ($item as &$subItem) {
                if (!is_null($subItem)) {
                    $subItem = strtr(
                        $subItem,
                        [
                            '\\' => '\\\\',
                            ';' => '\;',
                            ',' => '\,',
                            "\n" => '\n',
                            "\r" => '',
                        ]
                    );
                }
            }
            $item = implode(',', $item);
        }

        return implode($this->delimiter, $val);
    }

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        // Structured text values should always be returned as a single
        // array-item. Multi-value text should be returned as multiple items in
        // the top-array.
        if (in_array($this->name, $this->structuredValues)) {
            return [$this->getParts()];
        }

        return $this->getParts();
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'TEXT';
    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize()
    {
        // We need to kick in a special type of encoding, if it's a 2.1 vcard.
        if (Document::VCARD21 !== $this->root->getDocumentType()) {
            return parent::serialize();
        }

        $val = $this->getParts();

        if (isset($this->minimumPropertyValues[$this->name])) {
            $val = \array_pad($val, $this->minimumPropertyValues[$this->name], '');
        }

        // Imploding multiple parts into a single value, and splitting the
        // values with ;.
        if (\count($val) > 1) {
            foreach ($val as $k => $v) {
                $val[$k] = \str_replace(';', '\;', $v);
            }
            $val = \implode(';', $val);
        } else {
            $val = $val[0];
        }

        $str = $this->name;
        if ($this->group) {
            $str = $this->group.'.'.$this->name;
        }
        foreach ($this->parameters as $param) {
            if ('QUOTED-PRINTABLE' === $param->getValue()) {
                continue;
            }
            $str .= ';'.$param->serialize();
        }

        // If the resulting value contains a \n, we must encode it as
        // quoted-printable.
        if (false !== \strpos($val, "\n")) {
            $str .= ';ENCODING=QUOTED-PRINTABLE:';
            $lastLine = $str;
            $out = null;

            // The PHP built-in quoted-printable-encode does not correctly
            // encode newlines for us. Specifically, the \r\n sequence must in
            // vcards be encoded as =0D=OA and we must insert soft-newlines
            // every 75 bytes.
            for ($ii = 0; $ii < \strlen($val); ++$ii) {
                $ord = \ord($val[$ii]);
                // These characters are encoded as themselves.
                if ($ord >= 32 && $ord <= 126) {
                    $lastLine .= $val[$ii];
                } else {
                    $lastLine .= '='.\strtoupper(\bin2hex($val[$ii]));
                }
                if (\strlen($lastLine) >= 75) {
                    // Soft line break
                    $out .= $lastLine."=\r\n ";
                    $lastLine = null;
                }
            }
            if (!\is_null($lastLine)) {
                $out .= $lastLine."\r\n";
            }

            return $out;
        } else {
            $str .= ':'.$val;

            $str = \preg_replace(
                '/(
                    (?:^.)?         # 1 additional byte in first line because of missing single space (see next line)
                    .{1,74}         # max 75 bytes per line (1 byte is used for a single space added after every CRLF)
                    (?![\x80-\xbf]) # prevent splitting multibyte characters
                )/x',
                "$1\r\n ",
                $str
            );

            // remove single space after last CRLF
            return \substr($str, 0, -1);
        }
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    protected function xmlSerializeValue(Xml\Writer $writer)
    {
        $values = $this->getParts();

        $map = function ($items) use ($values, $writer) {
            foreach ($items as $i => $item) {
                $writer->writeElement(
                    $item,
                    !empty($values[$i]) ? $values[$i] : null
                );
            }
        };

        switch ($this->name) {
            // Special-casing the REQUEST-STATUS property.
            //
            // See:
            // http://tools.ietf.org/html/rfc6321#section-3.4.1.3
            case 'REQUEST-STATUS':
                $writer->writeElement('code', $values[0]);
                $writer->writeElement('description', $values[1]);

                if (isset($values[2])) {
                    $writer->writeElement('data', $values[2]);
                }
                break;

            case 'N':
                $map([
                    'surname',
                    'given',
                    'additional',
                    'prefix',
                    'suffix',
                ]);
                break;

            case 'GENDER':
                $map([
                    'sex',
                    'text',
                ]);
                break;

            case 'ADR':
                $map([
                    'pobox',
                    'ext',
                    'street',
                    'locality',
                    'region',
                    'code',
                    'country',
                ]);
                break;

            case 'CLIENTPIDMAP':
                $map([
                    'sourceid',
                    'uri',
                ]);
                break;

            default:
                parent::xmlSerializeValue($writer);
        }
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   - Node::REPAIR - If something is broken, and automatic repair may
     *                    be attempted.
     *
     * An array is returned with warnings.
     *
     * Every item in the array has the following properties:
     *    * level - (number between 1 and 3 with severity information)
     *    * message - (human readable message)
     *    * node - (reference to the offending node)
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        $warnings = parent::validate($options);

        if (isset($this->minimumPropertyValues[$this->name])) {
            $minimum = $this->minimumPropertyValues[$this->name];
            $parts = $this->getParts();
            if (count($parts) < $minimum) {
                $warnings[] = [
                    'level' => $options & self::REPAIR ? 1 : 3,
                    'message' => 'The '.$this->name.' property must have at least '.$minimum.' values. It only has '.count($parts),
                    'node' => $this,
                ];
                if ($options & self::REPAIR) {
                    $parts = array_pad($parts, $minimum, '');
                    $this->setParts($parts);
                }
            }
        }

        return $warnings;
    }
}

namespace Sabre\VObject\Property;

/**
 * FlatText property.
 *
 * This object represents certain TEXT values.
 *
 * Specifically, this property is used for text values where there is only 1
 * part. Semi-colons and colons will be de-escaped when deserializing, but if
 * any semi-colons or commas appear without a backslash, we will not assume
 * that they are delimiters.
 *
 * vCard 2.1 specifically has a whole bunch of properties where this may
 * happen, as it only defines a delimiter for a few properties.
 *
 * vCard 4.0 states something similar. An unescaped semi-colon _may_ be a
 * delimiter, depending on the property.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class FlatText extends Text
{
    /**
     * Field separator.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * Sets the value as a quoted-printable encoded string.
     *
     * Overriding this so we're not splitting on a ; delimiter.
     *
     * @param string $val
     */
    public function setQuotedPrintableValue($val)
    {
        $val = quoted_printable_decode($val);
        $this->setValue($val);
    }
}

namespace Sabre\VObject\Property;

/**
 * Unknown property.
 *
 * This object represents any properties not recognized by the parser.
 * This type of value has been introduced by the jCal, jCard specs.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Unknown extends Text
{
    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        return [$this->getRawMimeDirValue()];
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'UNKNOWN';
    }
}

namespace Sabre\VObject\Property;

use Sabre\VObject\Parameter;
use Sabre\VObject\Property;

/**
 * URI property.
 *
 * This object encodes URI values. vCard 2.1 calls these URL.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Uri extends Text
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string
     */
    public $delimiter = '';

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'URI';
    }

    /**
     * Returns an iterable list of children.
     *
     * @return array
     */
    public function parameters()
    {
        $parameters = parent::parameters();
        if (!isset($parameters['VALUE']) && in_array($this->name, ['URL', 'PHOTO'])) {
            // If we are encoding a URI value, and this URI value has no
            // VALUE=URI parameter, we add it anyway.
            //
            // This is not required by any spec, but both Apple iCal and Apple
            // AddressBook (at least in version 10.8) will trip over this if
            // this is not set, and so it improves compatibility.
            //
            // See Issue #227 and #235
            $parameters['VALUE'] = new Parameter($this->root, 'VALUE', 'URI');
        }

        return $parameters;
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        // Normally we don't need to do any type of unescaping for these
        // properties, however.. we've noticed that Google Contacts
        // specifically escapes the colon (:) with a backslash. While I have
        // no clue why they thought that was a good idea, I'm unescaping it
        // anyway.
        //
        // Good thing backslashes are not allowed in urls. Makes it easy to
        // assume that a backslash is always intended as an escape character.
        if ('URL' === $this->name) {
            $regex = '#  (?: (\\\\ (?: \\\\ | : ) ) ) #x';
            $matches = preg_split($regex, $val, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $newVal = '';
            foreach ($matches as $match) {
                switch ($match) {
                    case '\:':
                        $newVal .= ':';
                        break;
                    default:
                        $newVal .= $match;
                        break;
                }
            }
            $this->value = $newVal;
        } else {
            $this->value = strtr($val, ['\,' => ',']);
        }
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        if (is_array($this->value)) {
            $value = $this->value[0];
        } else {
            $value = $this->value;
        }

        return strtr($value, [',' => '\,']);
    }
}

namespace Sabre\VObject\Property;

use Sabre\VObject\Property;

/**
 * Integer property.
 *
 * This object represents INTEGER values. These are always a single integer.
 * They may be preceded by either + or -.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class IntegerValue extends Property
{
    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        $this->setValue((int) $val);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        return $this->value;
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'INTEGER';
    }

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        return [(int) $this->getValue()];
    }

    /**
     * Hydrate data from a XML subtree, as it would appear in a xCard or xCal
     * object.
     */
    public function setXmlValue(array $value)
    {
        $value = array_map('intval', $value);
        parent::setXmlValue($value);
    }
}

namespace Sabre\VObject\Property\ICalendar;

use DateTimeInterface;
use DateTimeZone;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\VObject\TimeZoneUtil;

/**
 * DateTime property.
 *
 * This object represents DATE-TIME values, as defined here:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.3.4
 *
 * This particular object has a bit of hackish magic that it may also in some
 * cases represent a DATE value. This is because it's a common usecase to be
 * able to change a DATE-TIME into a DATE.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class DateTime extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string|null
     */
    public $delimiter = ',';

    /**
     * Sets a multi-valued property.
     *
     * You may also specify DateTime objects here.
     */
    public function setParts(array $parts)
    {
        if (isset($parts[0]) && $parts[0] instanceof DateTimeInterface) {
            $this->setDateTimes($parts);
        } else {
            parent::setParts($parts);
        }
    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * Instead of strings, you may also use DateTime here.
     *
     * @param string|array|DateTimeInterface $value
     */
    public function setValue($value)
    {
        if (is_array($value) && isset($value[0]) && $value[0] instanceof DateTimeInterface) {
            $this->setDateTimes($value);
        } elseif ($value instanceof DateTimeInterface) {
            $this->setDateTimes([$value]);
        } else {
            parent::setValue($value);
        }
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        $this->setValue(explode($this->delimiter, $val));
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        return implode($this->delimiter, $this->getParts());
    }

    /**
     * Returns true if this is a DATE-TIME value, false if it's a DATE.
     *
     * @return bool
     */
    public function hasTime()
    {
        return 'DATE' !== strtoupper((string) $this['VALUE']);
    }

    /**
     * Returns true if this is a floating DATE or DATE-TIME.
     *
     * Note that DATE is always floating.
     */
    public function isFloating()
    {
        return
            !$this->hasTime() ||
            (
                !isset($this['TZID']) &&
                false === strpos($this->getValue(), 'Z')
            );
    }

    /**
     * Returns a date-time value.
     *
     * Note that if this property contained more than 1 date-time, only the
     * first will be returned. To get an array with multiple values, call
     * getDateTimes.
     *
     * If no timezone information is known, because it's either an all-day
     * property or floating time, we will use the DateTimeZone argument to
     * figure out the exact date.
     *
     * @param DateTimeZone $timeZone
     *
     * @return \DateTimeImmutable
     */
    public function getDateTime(?DateTimeZone $timeZone = null)
    {
        $dt = $this->getDateTimes($timeZone);
        if (!$dt) {
            return;
        }

        return $dt[0];
    }

    /**
     * Returns multiple date-time values.
     *
     * If no timezone information is known, because it's either an all-day
     * property or floating time, we will use the DateTimeZone argument to
     * figure out the exact date.
     *
     * @param DateTimeZone $timeZone
     *
     * @return \DateTimeImmutable[]
     * @return \DateTime[]
     */
    public function getDateTimes(?DateTimeZone $timeZone = null)
    {
        // Does the property have a TZID?
        $tzid = $this['TZID'];

        if ($tzid) {
            $timeZone = TimeZoneUtil::getTimeZone((string) $tzid, $this->root);
        }

        $dts = [];
        foreach ($this->getParts() as $part) {
            $dts[] = DateTimeParser::parse($part, $timeZone);
        }

        return $dts;
    }

    /**
     * Sets the property as a DateTime object.
     *
     * @param bool isFloating If set to true, timezones will be ignored
     */
    public function setDateTime(DateTimeInterface $dt, $isFloating = false)
    {
        $this->setDateTimes([$dt], $isFloating);
    }

    /**
     * Sets the property as multiple date-time objects.
     *
     * The first value will be used as a reference for the timezones, and all
     * the other values will be adjusted for that timezone
     *
     * @param DateTimeInterface[] $dt
     * @param bool isFloating If set to true, timezones will be ignored
     */
    public function setDateTimes(array $dt, $isFloating = false)
    {
        $values = [];

        if ($this->hasTime()) {
            $tz = null;
            $isUtc = false;

            foreach ($dt as $d) {
                if ($isFloating) {
                    $values[] = $d->format('Ymd\\THis');
                    continue;
                }
                if (is_null($tz)) {
                    $tz = $d->getTimeZone();
                    $isUtc = in_array($tz->getName(), ['UTC', 'GMT', 'Z', '+00:00']);
                    if (!$isUtc) {
                        $this->offsetSet('TZID', $tz->getName());
                    }
                } else {
                    $d = $d->setTimeZone($tz);
                }

                if ($isUtc) {
                    $values[] = $d->format('Ymd\\THis\\Z');
                } else {
                    $values[] = $d->format('Ymd\\THis');
                }
            }
            if ($isUtc || $isFloating) {
                $this->offsetUnset('TZID');
            }
        } else {
            foreach ($dt as $d) {
                $values[] = $d->format('Ymd');
            }
            $this->offsetUnset('TZID');
        }

        $this->value = $values;
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return $this->hasTime() ? 'DATE-TIME' : 'DATE';
    }

    /**
     * Returns the value, in the format it should be encoded for JSON.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        $dts = $this->getDateTimes();
        $hasTime = $this->hasTime();
        $isFloating = $this->isFloating();

        $tz = $dts[0]->getTimeZone();
        $isUtc = $isFloating ? false : in_array($tz->getName(), ['UTC', 'GMT', 'Z']);

        return array_map(
            function (DateTimeInterface $dt) use ($hasTime, $isUtc) {
                if ($hasTime) {
                    return $dt->format('Y-m-d\\TH:i:s').($isUtc ? 'Z' : '');
                } else {
                    return $dt->format('Y-m-d');
                }
            },
            $dts
        );
    }

    /**
     * Sets the json value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     */
    public function setJsonValue(array $value)
    {
        // dates and times in jCal have one difference to dates and times in
        // iCalendar. In jCal date-parts are separated by dashes, and
        // time-parts are separated by colons. It makes sense to just remove
        // those.
        $this->setValue(
            array_map(
                function ($item) {
                    return strtr($item, [':' => '', '-' => '']);
                },
                $value
            )
        );
    }

    /**
     * We need to intercept offsetSet, because it may be used to alter the
     * VALUE from DATE-TIME to DATE or vice-versa.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        parent::offsetSet($name, $value);
        if ('VALUE' !== strtoupper($name)) {
            return;
        }

        // This will ensure that dates are correctly encoded.
        $this->setDateTimes($this->getDateTimes());
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   Node::REPAIR - May attempt to automatically repair the problem.
     *
     * This method returns an array with detected problems.
     * Every element has the following properties:
     *
     *  * level - problem level.
     *  * message - A human-readable string describing the issue.
     *  * node - A reference to the problematic node.
     *
     * The level means:
     *   1 - The issue was repaired (only happens if REPAIR was turned on)
     *   2 - An inconsequential issue
     *   3 - A severe issue.
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        $messages = parent::validate($options);
        $valueType = $this->getValueType();
        $values = $this->getParts();
        foreach ($values as $value) {
            try {
                switch ($valueType) {
                    case 'DATE':
                        DateTimeParser::parseDate($value);
                        break;
                    case 'DATE-TIME':
                        DateTimeParser::parseDateTime($value);
                        break;
                }
            } catch (InvalidDataException $e) {
                $messages[] = [
                    'level' => 3,
                    'message' => 'The supplied value ('.$value.') is not a correct '.$valueType,
                    'node' => $this,
                ];
                break;
            }
        }

        return $messages;
    }
}

namespace Sabre\VObject\Property\ICalendar;

/**
 * DateTime property.
 *
 * This object represents DATE values, as defined here:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.3.5
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Date extends DateTime
{
}

namespace Sabre\VObject\Property\ICalendar;

use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Property;

/**
 * Duration property.
 *
 * This object represents DURATION values, as defined here:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.3.6
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Duration extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        $this->setValue(explode($this->delimiter, $val));
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        return implode($this->delimiter, $this->getParts());
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'DURATION';
    }

    /**
     * Returns a DateInterval representation of the Duration property.
     *
     * If the property has more than one value, only the first is returned.
     *
     * @return \DateInterval
     */
    public function getDateInterval()
    {
        $parts = $this->getParts();
        $value = $parts[0];

        return DateTimeParser::parseDuration($value);
    }
}

namespace Sabre\VObject\Property\ICalendar;

use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\Xml;

/**
 * Recur property.
 *
 * This object represents RECUR properties.
 * These values are just used for RRULE and the now deprecated EXRULE.
 *
 * The RRULE property may look something like this:
 *
 * RRULE:FREQ=MONTHLY;BYDAY=1,2,3;BYHOUR=5.
 *
 * This property exposes this as a key=>value array that is accessible using
 * getParts, and may be set using setParts.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Recur extends Property
{
    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     */
    public function setValue($value)
    {
        // If we're getting the data from json, we'll be receiving an object
        if ($value instanceof \StdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $newVal = [];
            foreach ($value as $k => $v) {
                if (is_string($v)) {
                    $v = strtoupper($v);

                    // The value had multiple sub-values
                    if (false !== strpos($v, ',')) {
                        $v = explode(',', $v);
                    }
                    if (0 === strcmp($k, 'until')) {
                        $v = strtr($v, [':' => '', '-' => '']);
                    }
                } elseif (is_array($v)) {
                    $v = array_map('strtoupper', $v);
                }

                $newVal[strtoupper($k)] = $v;
            }
            $this->value = $newVal;
        } elseif (is_string($value)) {
            $this->value = self::stringToArray($value);
        } else {
            throw new \InvalidArgumentException('You must either pass a string, or a key=>value array');
        }
    }

    /**
     * Returns the current value.
     *
     * This method will always return a singular value. If this was a
     * multi-value object, some decision will be made first on how to represent
     * it as a string.
     *
     * To get the correct multi-value version, use getParts.
     *
     * @return string
     */
    public function getValue()
    {
        $out = [];
        foreach ($this->value as $key => $value) {
            $out[] = $key.'='.(is_array($value) ? implode(',', $value) : $value);
        }

        return strtoupper(implode(';', $out));
    }

    /**
     * Sets a multi-valued property.
     */
    public function setParts(array $parts)
    {
        $this->setValue($parts);
    }

    /**
     * Returns a multi-valued property.
     *
     * This method always returns an array, if there was only a single value,
     * it will still be wrapped in an array.
     *
     * @return array
     */
    public function getParts()
    {
        return $this->value;
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     */
    public function setRawMimeDirValue($val)
    {
        $this->setValue($val);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue()
    {
        return $this->getValue();
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType()
    {
        return 'RECUR';
    }

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @return array
     */
    public function getJsonValue()
    {
        $values = [];
        foreach ($this->getParts() as $k => $v) {
            if (0 === strcmp($k, 'UNTIL')) {
                $date = new DateTime($this->root, null, $v);
                $values[strtolower($k)] = $date->getJsonValue()[0];
            } elseif (0 === strcmp($k, 'COUNT')) {
                $values[strtolower($k)] = intval($v);
            } else {
                $values[strtolower($k)] = $v;
            }
        }

        return [$values];
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    protected function xmlSerializeValue(Xml\Writer $writer)
    {
        $valueType = strtolower($this->getValueType());

        foreach ($this->getJsonValue() as $value) {
            $writer->writeElement($valueType, $value);
        }
    }

    /**
     * Parses an RRULE value string, and turns it into a struct-ish array.
     *
     * @param string $value
     *
     * @return array
     */
    public static function stringToArray($value)
    {
        $value = strtoupper($value);
        $newValue = [];
        foreach (explode(';', $value) as $part) {
            // Skipping empty parts.
            if (empty($part)) {
                continue;
            }

            $parts = explode('=', $part);

            if (2 !== count($parts)) {
                throw new InvalidDataException('The supplied iCalendar RRULE part is incorrect: '.$part);
            }

            list($partName, $partValue) = $parts;

            // The value itself had multiple values..
            if (false !== strpos($partValue, ',')) {
                $partValue = explode(',', $partValue);
            }
            $newValue[$partName] = $partValue;
        }

        return $newValue;
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   Node::REPAIR - May attempt to automatically repair the problem.
     *
     * This method returns an array with detected problems.
     * Every element has the following properties:
     *
     *  * level - problem level.
     *  * message - A human-readable string describing the issue.
     *  * node - A reference to the problematic node.
     *
     * The level means:
     *   1 - The issue was repaired (only happens if REPAIR was turned on)
     *   2 - An inconsequential issue
     *   3 - A severe issue.
     *
     * @param int $options
     *
     * @return array
     */
    public function validate($options = 0)
    {
        $repair = ($options & self::REPAIR);

        $warnings = parent::validate($options);
        $values = $this->getParts();

        foreach ($values as $key => $value) {
            if ('' === $value) {
                $warnings[] = [
                    'level' => $repair ? 1 : 3,
                    'message' => 'Invalid value for '.$key.' in '.$this->name,
                    'node' => $this,
                ];
                if ($repair) {
                    unset($values[$key]);
                }
            } elseif ('BYMONTH' == $key) {
                $byMonth = (array) $value;
                foreach ($byMonth as $i => $v) {
                    if (!is_numeric($v) || (int) $v < 1 || (int) $v > 12) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYMONTH in RRULE must have value(s) between 1 and 12!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && empty($values[$key])) {
                    unset($values[$key]);
                }
            } elseif ('BYWEEKNO' == $key) {
                $byWeekNo = (array) $value;
                foreach ($byWeekNo as $i => $v) {
                    if (!is_numeric($v) || (int) $v < -53 || 0 == (int) $v || (int) $v > 53) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYWEEKNO in RRULE must have value(s) from -53 to -1, or 1 to 53!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && empty($values[$key])) {
                    unset($values[$key]);
                }
            } elseif ('BYYEARDAY' == $key) {
                $byYearDay = (array) $value;
                foreach ($byYearDay as $i => $v) {
                    if (!is_numeric($v) || (int) $v < -366 || 0 == (int) $v || (int) $v > 366) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYYEARDAY in RRULE must have value(s) from -366 to -1, or 1 to 366!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && empty($values[$key])) {
                    unset($values[$key]);
                }
            }
        }
        if (!isset($values['FREQ'])) {
            $warnings[] = [
                'level' => $repair ? 1 : 3,
                'message' => 'FREQ is required in '.$this->name,
                'node' => $this,
            ];
            if ($repair) {
                $this->parent->remove($this);
            }
        }
        if ($repair) {
            $this->setValue($values);
        }

        return $warnings;
    }
}

namespace Sabre\VObject\Component;

use Sabre\VObject;

/**
 * (Subset of) VCalendar parser.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class VCalendar extends VObject\Document
{
    public static $defaultName = 'VCALENDAR';

    public static $componentMap = [
        'VCALENDAR' => self::class,
    ];

    public static $valueMap = [
        'DATE' => VObject\Property\ICalendar\Date::class,
        'DATE-TIME' => VObject\Property\ICalendar\DateTime::class,
        'DURATION' => VObject\Property\ICalendar\Duration::class,
        'INTEGER' => VObject\Property\IntegerValue::class,
        'RECUR' => VObject\Property\ICalendar\Recur::class,
        'TEXT' => VObject\Property\Text::class,
        'URI' => VObject\Property\Uri::class,
    ];

    public static $propertyMap = [
        'VERSION' => VObject\Property\FlatText::class,
        'METHOD' => VObject\Property\FlatText::class,
        'DESCRIPTION' => VObject\Property\FlatText::class,
        'LOCATION' => VObject\Property\FlatText::class,
        'PRIORITY' => VObject\Property\IntegerValue::class,
        'STATUS' => VObject\Property\FlatText::class,
        'SUMMARY' => VObject\Property\FlatText::class,
        'COMPLETED' => VObject\Property\ICalendar\DateTime::class,
        'DTEND' => VObject\Property\ICalendar\DateTime::class,
        'DUE' => VObject\Property\ICalendar\DateTime::class,
        'DTSTART' => VObject\Property\ICalendar\DateTime::class,
        'DURATION' => VObject\Property\ICalendar\Duration::class,
        'TZID' => VObject\Property\FlatText::class,
        'URL' => VObject\Property\Uri::class,
        'UID' => VObject\Property\FlatText::class,
        'RRULE' => VObject\Property\ICalendar\Recur::class,
        'ACTION' => VObject\Property\FlatText::class,
        'TRIGGER' => VObject\Property\ICalendar\Duration::class,
    ];

    public function getDocumentType()
    {
        return self::ICALENDAR20;
    }
}

namespace Sabre\VObject\Parser;

/**
 * Abstract parser.
 *
 * This class serves as a base-class for the different parsers.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
abstract class Parser
{
    /**
     * Turning on this option makes the parser more forgiving.
     *
     * In the case of the MimeDir parser, this means that the parser will
     * accept slashes and underscores in property names, and it will also
     * attempt to fix Microsoft vCard 2.1's broken line folding.
     */
    const OPTION_FORGIVING = 1;

    /**
     * If this option is turned on, any lines we cannot parse will be ignored
     * by the reader.
     */
    const OPTION_IGNORE_INVALID_LINES = 2;

    /**
     * Bitmask of parser options.
     *
     * @var int
     */
    protected $options;

    /**
     * Creates the parser.
     *
     * Optionally, it's possible to parse the input stream here.
     *
     * @param mixed $input
     * @param int   $options any parser options (OPTION constants)
     */
    public function __construct($input = null, $options = 0)
    {
        if (!is_null($input)) {
            $this->setInput($input);
        }
        $this->options = $options;
    }

    /**
     * This method starts the parsing process.
     *
     * If the input was not supplied during construction, it's possible to pass
     * it here instead.
     *
     * If either input or options are not supplied, the defaults will be used.
     *
     * @param mixed $input
     * @param int   $options
     *
     * @return array
     */
    abstract public function parse($input = null, $options = 0);

    /**
     * Sets the input data.
     *
     * @param mixed $input
     */
    abstract public function setInput($input);
}

namespace Sabre\VObject\Parser;

use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Document;
use Sabre\VObject\EofException;
use Sabre\VObject\Node;
use Sabre\VObject\ParseException;

/**
 * MimeDir parser.
 *
 * This class parses iCalendar 2.0 files and returns a
 * Sabre\VObject\Component\VCalendar.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class MimeDir extends Parser
{
    /**
     * The input stream.
     *
     * @var resource
     */
    protected $input;

    /**
     * Root component.
     *
     * @var Component
     */
    protected $root;

    /**
     * By default all input will be assumed to be UTF-8.
     *
     * However, both iCalendar and vCard might be encoded using different
     * character sets. The character set is usually set in the mime-type.
     *
     * If this is the case, use setEncoding to specify that a different
     * encoding will be used. If this is set, the parser will automatically
     * convert all incoming data to UTF-8.
     *
     * @var string
     */
    protected $charset = 'UTF-8';

    /**
     * The list of character sets we support when decoding.
     *
     * This would be a const expression but for now we need to support PHP 5.5
     */
    protected static $SUPPORTED_CHARSETS = [
        'UTF-8',
        'ISO-8859-1',
        'Windows-1252',
    ];

    /**
     * Parses an iCalendar or vCard file.
     *
     * Pass a stream or a string. If null is parsed, the existing buffer is
     * used.
     *
     * @param string|resource|null $input
     * @param int                  $options
     *
     * @return \Sabre\VObject\Document
     */
    public function parse($input = null, $options = 0)
    {
        $this->root = null;

        if (!is_null($input)) {
            $this->setInput($input);
        }

        if (!\is_resource($this->input)) {
            // Null was passed as input, but there was no existing input buffer
            // There is nothing to parse.
            throw new ParseException('No input provided to parse');
        }

        if (0 !== $options) {
            $this->options = $options;
        }

        $this->parseDocument();

        return $this->root;
    }

    /**
     * By default all input will be assumed to be UTF-8.
     *
     * However, both iCalendar and vCard might be encoded using different
     * character sets. The character set is usually set in the mime-type.
     *
     * If this is the case, use setEncoding to specify that a different
     * encoding will be used. If this is set, the parser will automatically
     * convert all incoming data to UTF-8.
     *
     * @param string $charset
     */
    public function setCharset($charset)
    {
        if (!in_array($charset, self::$SUPPORTED_CHARSETS)) {
            throw new \InvalidArgumentException('Unsupported encoding. (Supported encodings: '.implode(', ', self::$SUPPORTED_CHARSETS).')');
        }
        $this->charset = $charset;
    }

    /**
     * Sets the input buffer. Must be a string or stream.
     *
     * @param resource|string $input
     */
    public function setInput($input)
    {
        // Resetting the parser
        $this->lineIndex = 0;
        $this->startLine = 0;

        if (is_string($input)) {
            // Converting to a stream.
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $input);
            rewind($stream);
            $this->input = $stream;
        } elseif (is_resource($input)) {
            $this->input = $input;
        } else {
            throw new \InvalidArgumentException('This parser can only read from strings or streams.');
        }
    }

    /**
     * Parses an entire document.
     */
    protected function parseDocument()
    {
        $line = $this->readLine();

        // BOM is ZERO WIDTH NO-BREAK SPACE (U+FEFF).
        // It's 0xEF 0xBB 0xBF in UTF-8 hex.
        if (3 <= strlen($line)
            && 0xef === ord($line[0])
            && 0xbb === ord($line[1])
            && 0xbf === ord($line[2])) {
            $line = substr($line, 3);
        }

        switch (strtoupper($line)) {
            case 'BEGIN:VCALENDAR':
                $class = VCalendar::$componentMap['VCALENDAR'];
                break;
            default:
                throw new ParseException('This parser only supports VCALENDAR files');
        }

        $this->root = new $class([], false);

        while (true) {
            // Reading until we hit END:
            try {
                $line = $this->readLine();
            } catch (EofException $oEx) {
                $line = 'END:'.$this->root->name;
            }
            if ('END:' === strtoupper(substr($line, 0, 4))) {
                break;
            }
            $result = $this->parseLine($line);
            if ($result) {
                $this->root->add($result);
            }
        }

        $name = strtoupper(substr($line, 4));
        if ($name !== $this->root->name) {
            throw new ParseException('Invalid MimeDir file. expected: "END:'.$this->root->name.'" got: "END:'.$name.'"');
        }
    }

    /**
     * Parses a line, and if it hits a component, it will also attempt to parse
     * the entire component.
     *
     * @param string $line Unfolded line
     *
     * @return Node
     */
    protected function parseLine($line)
    {
        // Start of a new component
        if ('BEGIN:' === strtoupper(substr($line, 0, 6))) {
            if (substr($line, 6) === $this->root->name) {
                throw new ParseException('Invalid MimeDir file. Unexpected component: "'.$line.'" in document type '.$this->root->name);
            }
            $component = $this->root->createComponent(substr($line, 6), [], false);

            while (true) {
                // Reading until we hit END:
                $line = $this->readLine();
                if ('END:' === strtoupper(substr($line, 0, 4))) {
                    break;
                }
                $result = $this->parseLine($line);
                if ($result) {
                    $component->add($result);
                }
            }

            $name = strtoupper(substr($line, 4));
            if ($name !== $component->name) {
                throw new ParseException('Invalid MimeDir file. expected: "END:'.$component->name.'" got: "END:'.$name.'"');
            }

            return $component;
        } else {
            // Property reader
            $property = $this->readProperty($line);
            if (!$property) {
                // Ignored line
                return false;
            }

            return $property;
        }
    }

    /**
     * We need to look ahead 1 line every time to see if we need to 'unfold'
     * the next line.
     *
     * If that was not the case, we store it here.
     *
     * @var string|null
     */
    protected $lineBuffer;

    /**
     * The real current line number.
     */
    protected $lineIndex = 0;

    /**
     * In the case of unfolded lines, this property holds the line number for
     * the start of the line.
     *
     * @var int
     */
    protected $startLine = 0;

    /**
     * Contains a 'raw' representation of the current line.
     *
     * @var string
     */
    protected $rawLine;

    /**
     * Reads a single line from the buffer.
     *
     * This method strips any newlines and also takes care of unfolding.
     *
     * @throws \Sabre\VObject\EofException
     *
     * @return string
     */
    protected function readLine()
    {
        if (!\is_null($this->lineBuffer)) {
            $rawLine = $this->lineBuffer;
            $this->lineBuffer = null;
        } else {
            do {
                $eof = \feof($this->input);

                $rawLine = \fgets($this->input);

                if ($eof || (\feof($this->input) && false === $rawLine)) {
                    throw new EofException('End of document reached prematurely');
                }
                if (false === $rawLine) {
                    throw new ParseException('Error reading from input stream');
                }
                $rawLine = \rtrim($rawLine, "\r\n");
            } while ('' === $rawLine); // Skipping empty lines
            ++$this->lineIndex;
        }
        $line = $rawLine;

        $this->startLine = $this->lineIndex;

        // Looking ahead for folded lines.
        while (true) {
            $nextLine = \rtrim(\fgets($this->input), "\r\n");
            ++$this->lineIndex;
            if (!$nextLine) {
                break;
            }
            if ("\t" === $nextLine[0] || ' ' === $nextLine[0]) {
                $curLine = \substr($nextLine, 1);
                $line .= $curLine;
                $rawLine .= "\n ".$curLine;
            } else {
                $this->lineBuffer = $nextLine;
                break;
            }
        }
        $this->rawLine = $rawLine;

        return $line;
    }

    /**
     * Reads a property or component from a line.
     */
    protected function readProperty($line)
    {
        if ($this->options & self::OPTION_FORGIVING) {
            $propNameToken = 'A-Z0-9\-\._\\/';
        } else {
            $propNameToken = 'A-Z0-9\-\.';
        }

        $paramNameToken = 'A-Z0-9\-';
        $safeChar = '^";:,';
        $qSafeChar = '^"';

        $regex = "/
            ^(?P<name> [$propNameToken]+ ) (?=[;:])        # property name
            |
            (?<=:)(?P<propValue> .+)$                      # property value
            |
            ;(?P<paramName> [$paramNameToken]+) (?=[=;:])  # parameter name
            |
            (=|,)(?P<paramValue>                           # parameter value
                (?: [$safeChar]*) |
                \"(?: [$qSafeChar]+)\"
            ) (?=[;:,])
            /xi";

        //echo $regex, "\n"; exit();
        preg_match_all($regex, $line, $matches, PREG_SET_ORDER);

        $property = [
            'name' => null,
            'parameters' => [],
            'value' => null,
        ];

        $lastParam = null;

        /*
         * Looping through all the tokens.
         *
         * Note that we are looping through them in reverse order, because if a
         * sub-pattern matched, the subsequent named patterns will not show up
         * in the result.
         */
        foreach ($matches as $match) {
            if (isset($match['paramValue'])) {
                if ($match['paramValue'] && '"' === $match['paramValue'][0]) {
                    $value = substr($match['paramValue'], 1, -1);
                } else {
                    $value = $match['paramValue'];
                }

                $value = $this->unescapeParam($value);

                if (is_null($lastParam)) {
                    if ($this->options & self::OPTION_IGNORE_INVALID_LINES) {
                        // When the property can't be matched and the configuration
                        // option is set to ignore invalid lines, we ignore this line
                        // This can happen when servers provide faulty data as iCloud
                        //  frequently does with X-APPLE-STRUCTURED-LOCATION
                        continue;
                    }
                    throw new ParseException('Invalid Mimedir file. Line starting at '.$this->startLine.' did not follow iCalendar/vCard conventions');
                }
                if (is_null($property['parameters'][$lastParam])) {
                    $property['parameters'][$lastParam] = $value;
                } elseif (is_array($property['parameters'][$lastParam])) {
                    $property['parameters'][$lastParam][] = $value;
                } elseif ($property['parameters'][$lastParam] === $value) {
                    // When the current value of the parameter is the same as the
                    // new one, then we can leave the current parameter as it is.
                } else {
                    $property['parameters'][$lastParam] = [
                        $property['parameters'][$lastParam],
                        $value,
                    ];
                }
                continue;
            }
            if (isset($match['paramName'])) {
                $lastParam = strtoupper($match['paramName']);
                if (!isset($property['parameters'][$lastParam])) {
                    $property['parameters'][$lastParam] = null;
                }
                continue;
            }
            if (isset($match['propValue'])) {
                $property['value'] = $match['propValue'];
                continue;
            }
            if (isset($match['name']) && $match['name']) {
                $property['name'] = strtoupper($match['name']);
                continue;
            }

            // @codeCoverageIgnoreStart
            throw new \LogicException('This code should not be reachable');
            // @codeCoverageIgnoreEnd
        }

        if (is_null($property['value'])) {
            $property['value'] = '';
        }
        if (!$property['name']) {
            if ($this->options & self::OPTION_IGNORE_INVALID_LINES) {
                return false;
            }
            throw new ParseException('Invalid Mimedir file. Line starting at '.$this->startLine.' did not follow iCalendar/vCard conventions');
        }

        // vCard 2.1 states that parameters may appear without a name, and only
        // a value. We can deduce the value based on its name.
        //
        // Our parser will get those as parameters without a value instead, so
        // we're filtering these parameters out first.
        $namedParameters = [];
        $namelessParameters = [];

        foreach ($property['parameters'] as $name => $value) {
            if (!is_null($value)) {
                $namedParameters[$name] = $value;
            } else {
                $namelessParameters[] = $name;
            }
        }

        $propObj = $this->root->createProperty($property['name'], null, $namedParameters, null, $this->startLine, $line);

        foreach ($namelessParameters as $namelessParameter) {
            $propObj->add(null, $namelessParameter);
        }

        if (isset($propObj['ENCODING']) && 'QUOTED-PRINTABLE' === strtoupper($propObj['ENCODING'])) {
            $propObj->setQuotedPrintableValue($this->extractQuotedPrintableValue());
        } else {
            $charset = $this->charset;
            if (Document::VCARD21 === $this->root->getDocumentType() && isset($propObj['CHARSET'])) {
                // vCard 2.1 allows the character set to be specified per property.
                $charset = (string) $propObj['CHARSET'];
            }
            switch (strtolower($charset)) {
                case 'utf-8':
                    break;
                case 'windows-1252':
                case 'iso-8859-1':
                    $property['value'] = mb_convert_encoding($property['value'], 'UTF-8', $charset);
                    break;
                default:
                    throw new ParseException('Unsupported CHARSET: '.$propObj['CHARSET']);
            }
            $propObj->setRawMimeDirValue($property['value']);
        }

        return $propObj;
    }

    /**
     * Unescapes a property value.
     *
     * vCard 2.1 says:
     *   * Semi-colons must be escaped in some property values, specifically
     *     ADR, ORG and N.
     *   * Semi-colons must be escaped in parameter values, because semi-colons
     *     are also use to separate values.
     *   * No mention of escaping backslashes with another backslash.
     *   * newlines are not escaped either, instead QUOTED-PRINTABLE is used to
     *     span values over more than 1 line.
     *
     * vCard 3.0 says:
     *   * (rfc2425) Backslashes, newlines (\n or \N) and comma's must be
     *     escaped, all time time.
     *   * Comma's are used for delimiters in multiple values
     *   * (rfc2426) Adds to to this that the semi-colon MUST also be escaped,
     *     as in some properties semi-colon is used for separators.
     *   * Properties using semi-colons: N, ADR, GEO, ORG
     *   * Both ADR and N's individual parts may be broken up further with a
     *     comma.
     *   * Properties using commas: NICKNAME, CATEGORIES
     *
     * vCard 4.0 (rfc6350) says:
     *   * Commas must be escaped.
     *   * Semi-colons may be escaped, an unescaped semi-colon _may_ be a
     *     delimiter, depending on the property.
     *   * Backslashes must be escaped
     *   * Newlines must be escaped as either \N or \n.
     *   * Some compound properties may contain multiple parts themselves, so a
     *     comma within a semi-colon delimited property may also be unescaped
     *     to denote multiple parts _within_ the compound property.
     *   * Text-properties using semi-colons: N, ADR, ORG, CLIENTPIDMAP.
     *   * Text-properties using commas: NICKNAME, RELATED, CATEGORIES, PID.
     *
     * Even though the spec says that commas must always be escaped, the
     * example for GEO in Section 6.5.2 seems to violate this.
     *
     * iCalendar 2.0 (rfc5545) says:
     *   * Commas or semi-colons may be used as delimiters, depending on the
     *     property.
     *   * Commas, semi-colons, backslashes, newline (\N or \n) are always
     *     escaped, unless they are delimiters.
     *   * Colons shall not be escaped.
     *   * Commas can be considered the 'default delimiter' and is described as
     *     the delimiter in cases where the order of the multiple values is
     *     insignificant.
     *   * Semi-colons are described as the delimiter for 'structured values'.
     *     They are specifically used in Semi-colons are used as a delimiter in
     *     REQUEST-STATUS, RRULE, GEO and EXRULE. EXRULE is deprecated however.
     *
     * Now for the parameters
     *
     * If delimiter is not set (empty string) this method will just return a string.
     * If it's a comma or a semi-colon the string will be split on those
     * characters, and always return an array.
     *
     * @param string $input
     * @param string $delimiter
     *
     * @return string|string[]
     */
    public static function unescapeValue($input, $delimiter = ';')
    {
        $regex = '#  (?: (\\\\ (?: \\\\ | N | n | ; | , ) )';
        if ($delimiter) {
            $regex .= ' | ('.$delimiter.')';
        }
        $regex .= ') #x';

        $matches = preg_split($regex, $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $resultArray = [];
        $result = '';

        foreach ($matches as $match) {
            switch ($match) {
                case '\\\\':
                    $result .= '\\';
                    break;
                case '\N':
                case '\n':
                    $result .= "\n";
                    break;
                case '\;':
                    $result .= ';';
                    break;
                case '\,':
                    $result .= ',';
                    break;
                case $delimiter:
                    $resultArray[] = $result;
                    $result = '';
                    break;
                default:
                    $result .= $match;
                    break;
            }
        }

        $resultArray[] = $result;

        return $delimiter ? $resultArray : $result;
    }

    /**
     * Unescapes a parameter value.
     *
     * vCard 2.1:
     *   * Does not mention a mechanism for this. In addition, double quotes
     *     are never used to wrap values.
     *   * This means that parameters can simply not contain colons or
     *     semi-colons.
     *
     * vCard 3.0 (rfc2425, rfc2426):
     *   * Parameters _may_ be surrounded by double quotes.
     *   * If this is not the case, semi-colon, colon and comma may simply not
     *     occur (the comma used for multiple parameter values though).
     *   * If it is surrounded by double-quotes, it may simply not contain
     *     double-quotes.
     *   * This means that a parameter can in no case encode double-quotes, or
     *     newlines.
     *
     * vCard 4.0 (rfc6350)
     *   * Behavior seems to be identical to vCard 3.0
     *
     * iCalendar 2.0 (rfc5545)
     *   * Behavior seems to be identical to vCard 3.0
     *
     * Parameter escaping mechanism (rfc6868) :
     *   * This rfc describes a new way to escape parameter values.
     *   * New-line is encoded as ^n
     *   * ^ is encoded as ^^.
     *   * " is encoded as ^'
     *
     * @param string $input
     */
    private function unescapeParam($input)
    {
        return
            preg_replace_callback(
                '#(\^(\^|n|\'))#',
                function ($matches) {
                    switch ($matches[2]) {
                        case 'n':
                            return "\n";
                        case '^':
                            return '^';
                        case '\'':
                            return '"';

                    // @codeCoverageIgnoreStart
                    }
                    // @codeCoverageIgnoreEnd
                },
                $input
            );
    }

    /**
     * Gets the full quoted printable value.
     *
     * We need a special method for this, because newlines have both a meaning
     * in vCards, and in QuotedPrintable.
     *
     * This method does not do any decoding.
     *
     * @return string
     */
    private function extractQuotedPrintableValue()
    {
        // We need to parse the raw line again to get the start of the value.
        //
        // We are basically looking for the first colon (:), but we need to
        // skip over the parameters first, as they may contain one.
        $regex = '/^
            (?: [^:])+ # Anything but a colon
            (?: "[^"]")* # A parameter in double quotes
            : # start of the value we really care about
            (.*)$
        /xs';

        preg_match($regex, $this->rawLine, $matches);

        $value = $matches[1];
        // Removing the first whitespace character from every line. Kind of
        // like unfolding, but we keep the newline.
        $value = str_replace("\n ", "\n", $value);

        // Microsoft products don't always correctly fold lines, they may be
        // missing a whitespace. So if 'forgiving' is turned on, we will take
        // those as well.
        if ($this->options & self::OPTION_FORGIVING) {
            while ('=' === substr($value, -1) && $this->lineBuffer) {
                // Reading the line
                $this->readLine();
                // Grabbing the raw form
                $value .= "\n".$this->rawLine;
            }
        }

        return $value;
    }
}

namespace Sabre\VObject;

/**
 * iCalendar reader.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Reader
{
    const OPTION_FORGIVING = 1;
    const OPTION_IGNORE_INVALID_LINES = 2;

    public static function read($data, $options = 0, $charset = 'UTF-8')
    {
        $parser = new Parser\MimeDir();
        $parser->setCharset($charset);
        return $parser->parse($data, $options);
    }
}

namespace Sabre\VObject\Recur;

use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;

/**
 * RRuleParser.
 *
 * This class receives an RRULE string, and allows you to iterate to get a list
 * of dates in that recurrence.
 *
 * For instance, passing: FREQ=DAILY;LIMIT=5 will cause the iterator to contain
 * 5 items, one for each day.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class RRuleIterator implements Iterator
{
    /**
     * Constant denoting the upper limit on how long into the future
     * we want to iterate. The value is a unix timestamp and currently
     * corresponds to the datetime 9999-12-31 11:59:59 UTC.
     */
    const dateUpperLimit = 253402300799;

    /**
     * Creates the Iterator.
     *
     * @param string|array $rrule
     */
    public function __construct($rrule, DateTimeInterface $start)
    {
        $this->startDate = $start;
        $this->parseRRule($rrule);
        $this->currentDate = clone $this->startDate;
    }

    /* Implementation of the Iterator interface {{{ */

    #[\ReturnTypeWillChange]
    public function current()
    {
        if (!$this->valid()) {
            return;
        }

        return clone $this->currentDate;
    }

    /**
     * Returns the current item number.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->counter;
    }

    /**
     * Returns whether the current item is a valid item for the recurrence
     * iterator. This will return false if we've gone beyond the UNTIL or COUNT
     * statements.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        if (null === $this->currentDate) {
            return false;
        }
        if (!is_null($this->count)) {
            return $this->counter < $this->count;
        }

        return is_null($this->until) || $this->currentDate <= $this->until;
    }

    /**
     * Resets the iterator.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->currentDate = clone $this->startDate;
        $this->counter = 0;
    }

    /**
     * Goes on to the next iteration.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        // Otherwise, we find the next event in the normal RRULE
        // sequence.
        switch ($this->frequency) {
            case 'hourly':
                $this->nextHourly();
                break;

            case 'daily':
                $this->nextDaily();
                break;

            case 'weekly':
                $this->nextWeekly();
                break;

            case 'monthly':
                $this->nextMonthly();
                break;

            case 'yearly':
                $this->nextYearly();
                break;
        }
        ++$this->counter;
    }

    /* End of Iterator implementation }}} */

    /**
     * Returns true if this recurring event never ends.
     *
     * @return bool
     */
    public function isInfinite()
    {
        return !$this->count && !$this->until;
    }

    /**
     * This method allows you to quickly go to the next occurrence after the
     * specified date.
     */
    public function fastForward(DateTimeInterface $dt)
    {
        while ($this->valid() && $this->currentDate < $dt) {
            $this->next();
        }
    }

    /**
     * The reference start date/time for the rrule.
     *
     * All calculations are based on this initial date.
     *
     * @var DateTimeInterface
     */
    protected $startDate;

    /**
     * The date of the current iteration. You can get this by calling
     * ->current().
     *
     * @var DateTimeInterface
     */
    protected $currentDate;

    /**
     * The number of hours that the next occurrence of an event
     * jumped forward, usually because summer time started and
     * the requested time-of-day like 0230 did not exist on that
     * day. And so the event was scheduled 1 hour later at 0330.
     */
    protected $hourJump = 0;

    /**
     * Frequency is one of: secondly, minutely, hourly, daily, weekly, monthly,
     * yearly.
     *
     * @var string
     */
    protected $frequency;

    /**
     * The number of recurrences, or 'null' if infinitely recurring.
     *
     * @var int
     */
    protected $count;

    /**
     * The interval.
     *
     * If for example frequency is set to daily, interval = 2 would mean every
     * 2 days.
     *
     * @var int
     */
    protected $interval = 1;

    /**
     * The last instance of this recurrence, inclusively.
     *
     * @var DateTimeInterface|null
     */
    protected $until;

    /**
     * Which seconds to recur.
     *
     * This is an array of integers (between 0 and 60)
     *
     * @var array
     */
    protected $bySecond;

    /**
     * Which minutes to recur.
     *
     * This is an array of integers (between 0 and 59)
     *
     * @var array
     */
    protected $byMinute;

    /**
     * Which hours to recur.
     *
     * This is an array of integers (between 0 and 23)
     *
     * @var array
     */
    protected $byHour;

    /**
     * The current item in the list.
     *
     * You can get this number with the key() method.
     *
     * @var int
     */
    protected $counter = 0;

    /**
     * Which weekdays to recur.
     *
     * This is an array of weekdays
     *
     * This may also be preceded by a positive or negative integer. If present,
     * this indicates the nth occurrence of a specific day within the monthly or
     * yearly rrule. For instance, -2TU indicates the second-last tuesday of
     * the month, or year.
     *
     * @var array
     */
    protected $byDay;

    /**
     * Which days of the month to recur.
     *
     * This is an array of days of the months (1-31). The value can also be
     * negative. -5 for instance means the 5th last day of the month.
     *
     * @var array
     */
    protected $byMonthDay;

    /**
     * Which days of the year to recur.
     *
     * This is an array with days of the year (1 to 366). The values can also
     * be negative. For instance, -1 will always represent the last day of the
     * year. (December 31st).
     *
     * @var array
     */
    protected $byYearDay;

    /**
     * Which week numbers to recur.
     *
     * This is an array of integers from 1 to 53. The values can also be
     * negative. -1 will always refer to the last week of the year.
     *
     * @var array
     */
    protected $byWeekNo;

    /**
     * Which months to recur.
     *
     * This is an array of integers from 1 to 12.
     *
     * @var array
     */
    protected $byMonth;

    /**
     * Which items in an existing st to recur.
     *
     * These numbers work together with an existing by* rule. It specifies
     * exactly which items of the existing by-rule to filter.
     *
     * Valid values are 1 to 366 and -1 to -366. As an example, this can be
     * used to recur the last workday of the month.
     *
     * This would be done by setting frequency to 'monthly', byDay to
     * 'MO,TU,WE,TH,FR' and bySetPos to -1.
     *
     * @var array
     */
    protected $bySetPos;

    /**
     * When the week starts.
     *
     * @var string
     */
    protected $weekStart = 'MO';

    /* Functions that advance the iterator {{{ */

    /**
     * Gets the original start time of the RRULE.
     *
     * The value is formatted as a string with 24-hour:minute:second
     */
    protected function startTime(): string
    {
        return $this->startDate->format('H:i:s');
    }

    /**
     * Advances currentDate by the interval.
     * The time is set from the original startDate.
     * If the recurrence is on a day when summer time started, then the
     * time on that day may have jumped forward, for example, from 0230 to 0330.
     * Using the original time means that the next recurrence will be calculated
     * based on the original start time and the day/week/month/year interval.
     * So the start time of the next occurrence can correctly revert to 0230.
     */
    protected function advanceTheDate(string $interval): void
    {
        $this->currentDate = $this->currentDate->modify($interval.' '.$this->startTime());
    }

    /**
     * Does the processing for adjusting the time of multi-hourly events when summer time starts.
     */
    protected function adjustForTimeJumpsOfHourlyEvent(DateTimeInterface $previousEventDateTime): void
    {
        if (0 === $this->hourJump) {
            // Remember if the clock time jumped forward on the next occurrence.
            // That happens if the next event time is on a day when summer time starts
            // and the event time is in the non-existent hour of the day.
            // For example, an event that normally starts at 02:30 will
            // have to start at 03:30 on that day.
            // If the interval is just 1 hour, then there is no "jumping back" to do.
            // The events that day will happen, for example, at 0030 0130 0330 0430 0530...
            if ($this->interval > 1) {
                $expectedHourOfNextDate = ((int) $previousEventDateTime->format('G') + $this->interval) % 24;
                $actualHourOfNextDate = (int) $this->currentDate->format('G');
                $this->hourJump = $actualHourOfNextDate - $expectedHourOfNextDate;
            }
        } else {
            // The hour "jumped" for the previous occurrence, to avoid the non-existent time.
            // currentDate got set ahead by (usually) 1 hour on that day.
            // Adjust it back for this next occurrence.
            $this->currentDate = $this->currentDate->sub(new \DateInterval('PT'.$this->hourJump.'H'));
            $this->hourJump = 0;
        }
    }

    /**
     * Does the processing for advancing the iterator for hourly frequency.
     */
    protected function nextHourly()
    {
        $previousEventDateTime = clone $this->currentDate;
        $this->currentDate = $this->currentDate->modify('+'.$this->interval.' hours');
        $this->adjustForTimeJumpsOfHourlyEvent($previousEventDateTime);
    }

    /**
     * Does the processing for advancing the iterator for daily frequency.
     */
    protected function nextDaily()
    {
        if (!$this->byHour && !$this->byDay) {
            $this->advanceTheDate('+'.$this->interval.' days');

            return;
        }

        $recurrenceHours = [];
        if (!empty($this->byHour)) {
            $recurrenceHours = $this->getHours();
        }

        $recurrenceDays = [];
        if (!empty($this->byDay)) {
            $recurrenceDays = $this->getDays();
        }

        $recurrenceMonths = [];
        if (!empty($this->byMonth)) {
            $recurrenceMonths = $this->getMonths();
        }

        do {
            if ($this->byHour) {
                if ('23' == $this->currentDate->format('G')) {
                    // to obey the interval rule
                    $this->currentDate = $this->currentDate->modify('+'.($this->interval - 1).' days');
                }

                $this->currentDate = $this->currentDate->modify('+1 hours');
            } else {
                $this->currentDate = $this->currentDate->modify('+'.$this->interval.' days');
            }

            // Current month of the year
            $currentMonth = $this->currentDate->format('n');

            // Current day of the week
            $currentDay = $this->currentDate->format('w');

            // Current hour of the day
            $currentHour = $this->currentDate->format('G');

            if ($this->currentDate->getTimestamp() > self::dateUpperLimit) {
                $this->currentDate = null;

                return;
            }
        } while (
            ($this->byDay && !in_array($currentDay, $recurrenceDays)) ||
            ($this->byHour && !in_array($currentHour, $recurrenceHours)) ||
            ($this->byMonth && !in_array($currentMonth, $recurrenceMonths))
        );
    }

    /**
     * Does the processing for advancing the iterator for weekly frequency.
     */
    protected function nextWeekly()
    {
        if (!$this->byHour && !$this->byDay) {
            $this->advanceTheDate('+'.$this->interval.' weeks');

            return;
        }

        $recurrenceHours = [];
        if ($this->byHour) {
            $recurrenceHours = $this->getHours();
        }

        $recurrenceDays = [];
        if ($this->byDay) {
            $recurrenceDays = $this->getDays();
        }

        // First day of the week:
        $firstDay = $this->dayMap[$this->weekStart];

        do {
            if ($this->byHour) {
                $this->currentDate = $this->currentDate->modify('+1 hours');
            } else {
                $this->advanceTheDate('+1 days');
            }

            // Current day of the week
            $currentDay = (int) $this->currentDate->format('w');

            // Current hour of the day
            $currentHour = (int) $this->currentDate->format('G');

            // We need to roll over to the next week
            if ($currentDay === $firstDay && (!$this->byHour || '0' == $currentHour)) {
                $this->currentDate = $this->currentDate->modify('+'.($this->interval - 1).' weeks');

                // We need to go to the first day of this week, but only if we
                // are not already on this first day of this week.
                if ($this->currentDate->format('w') != $firstDay) {
                    $this->currentDate = $this->currentDate->modify('last '.$this->dayNames[$this->dayMap[$this->weekStart]]);
                }
            }

            // We have a match
        } while (($this->byDay && !in_array($currentDay, $recurrenceDays)) || ($this->byHour && !in_array($currentHour, $recurrenceHours)));
    }

    /**
     * Does the processing for advancing the iterator for monthly frequency.
     */
    protected function nextMonthly()
    {
        $currentDayOfMonth = $this->currentDate->format('j');
        if (!$this->byMonthDay && !$this->byDay) {
            // If the current day is higher than the 28th, rollover can
            // occur to the next month. We Must skip these invalid
            // entries.
            if ($currentDayOfMonth < 29) {
                $this->advanceTheDate('+'.$this->interval.' months');
            } else {
                $increase = 0;
                do {
                    ++$increase;
                    $tempDate = clone $this->currentDate;
                    $tempDate = $tempDate->modify('+ '.($this->interval * $increase).' months '.$this->startTime());
                } while ($tempDate->format('j') != $currentDayOfMonth);
                $this->currentDate = $tempDate;
            }

            return;
        }

        $occurrence = -1;
        while (true) {
            $occurrences = $this->getMonthlyOccurrences();

            foreach ($occurrences as $occurrence) {
                // The first occurrence thats higher than the current
                // day of the month wins.
                if ($occurrence > $currentDayOfMonth) {
                    break 2;
                }
            }

            // If we made it all the way here, it means there were no
            // valid occurrences, and we need to advance to the next
            // month.
            //
            // This line does not currently work in hhvm. Temporary workaround
            // follows:
            // $this->currentDate->modify('first day of this month');
            $this->currentDate = new DateTimeImmutable($this->currentDate->format('Y-m-1 H:i:s'), $this->currentDate->getTimezone());
            // end of workaround
            $this->currentDate = $this->currentDate->modify('+ '.$this->interval.' months');

            // This goes to 0 because we need to start counting at the
            // beginning.
            $currentDayOfMonth = 0;

            // For some reason the "until" parameter was not being used here,
            // that's why the workaround of the 10000 year bug was needed at all
            // let's stop it before the "until" parameter date
            if ($this->until && $this->currentDate->getTimestamp() >= $this->until->getTimestamp()) {
                return;
            }

            // To prevent running this forever (better: until we hit the max date of DateTimeImmutable) we simply
            // stop at 9999-12-31. Looks like the year 10000 problem is not solved in php ....
            if ($this->currentDate->getTimestamp() > self::dateUpperLimit) {
                $this->currentDate = null;

                return;
            }
        }

        // Set the currentDate to the year and month that we are in, and the day of the month that we have selected.
        // That day could be a day when summer time starts, and if the time of the event is, for example, 0230,
        // then 0230 will not be a valid time on that day. So always apply the start time from the original startDate.
        // The "modify" method will set the time forward to 0330, for example, if needed.
        $this->currentDate = $this->currentDate->setDate(
            (int) $this->currentDate->format('Y'),
            (int) $this->currentDate->format('n'),
            (int) $occurrence
        )->modify($this->startTime());
    }

    /**
     * Does the processing for advancing the iterator for yearly frequency.
     */
    protected function nextYearly()
    {
        $currentMonth = $this->currentDate->format('n');
        $currentYear = $this->currentDate->format('Y');
        $currentDayOfMonth = $this->currentDate->format('j');

        // No sub-rules, so we just advance by year
        if (empty($this->byMonth)) {
            // Unless it was a leap day!
            if (2 == $currentMonth && 29 == $currentDayOfMonth) {
                $counter = 0;
                do {
                    ++$counter;
                    // Here we increase the year count by the interval, until
                    // we hit a date that's also in a leap year.
                    //
                    // We could just find the next interval that's dividable by
                    // 4, but that would ignore the rule that there's no leap
                    // year every year that's dividable by a 100, but not by
                    // 400. (1800, 1900, 2100). So we just rely on the datetime
                    // functions instead.
                    $nextDate = clone $this->currentDate;
                    $nextDate = $nextDate->modify('+ '.($this->interval * $counter).' years');
                } while (2 != $nextDate->format('n'));

                $this->currentDate = $nextDate;

                return;
            }

            if (null !== $this->byWeekNo) { // byWeekNo is an array with values from -53 to -1, or 1 to 53
                $dayOffsets = [];
                if ($this->byDay) {
                    foreach ($this->byDay as $byDay) {
                        $dayOffsets[] = $this->dayMap[$byDay];
                    }
                } else {   // default is Monday
                    $dayOffsets[] = 1;
                }

                $currentYear = $this->currentDate->format('Y');

                while (true) {
                    $checkDates = [];

                    // loop through all WeekNo and Days to check all the combinations
                    foreach ($this->byWeekNo as $byWeekNo) {
                        foreach ($dayOffsets as $dayOffset) {
                            $date = clone $this->currentDate;
                            $date = $date->setISODate($currentYear, $byWeekNo, $dayOffset);

                            if ($date > $this->currentDate) {
                                $checkDates[] = $date;
                            }
                        }
                    }

                    if (count($checkDates) > 0) {
                        $this->currentDate = min($checkDates);

                        return;
                    }

                    // if there is no date found, check the next year
                    $currentYear += $this->interval;
                }
            }

            if (null !== $this->byYearDay) { // byYearDay is an array with values from -366 to -1, or 1 to 366
                $dayOffsets = [];
                if ($this->byDay) {
                    foreach ($this->byDay as $byDay) {
                        $dayOffsets[] = $this->dayMap[$byDay];
                    }
                } else {   // default is Monday-Sunday
                    $dayOffsets = [1, 2, 3, 4, 5, 6, 7];
                }

                $currentYear = $this->currentDate->format('Y');

                while (true) {
                    $checkDates = [];

                    // loop through all YearDay and Days to check all the combinations
                    foreach ($this->byYearDay as $byYearDay) {
                        $date = clone $this->currentDate;
                        if ($byYearDay > 0) {
                            $date = $date->setDate($currentYear, 1, 1);
                            $date = $date->add(new \DateInterval('P'.($byYearDay - 1).'D'));
                        } else {
                            $date = $date->setDate($currentYear, 12, 31);
                            $date = $date->sub(new \DateInterval('P'.abs($byYearDay + 1).'D'));
                        }

                        if ($date > $this->currentDate && in_array($date->format('N'), $dayOffsets)) {
                            $checkDates[] = $date;
                        }
                    }

                    if (count($checkDates) > 0) {
                        $this->currentDate = min($checkDates);

                        return;
                    }

                    // if there is no date found, check the next year
                    $currentYear += $this->interval;
                }
            }

            // The easiest form
            $this->advanceTheDate('+'.$this->interval.' years');

            return;
        }

        $currentMonth = $this->currentDate->format('n');
        $currentYear = $this->currentDate->format('Y');
        $currentDayOfMonth = $this->currentDate->format('j');

        $advancedToNewMonth = false;

        // If we got a byDay or getMonthDay filter, we must first expand
        // further.
        if ($this->byDay || $this->byMonthDay) {
            $occurrence = -1;
            while (true) {
                $occurrences = $this->getMonthlyOccurrences();

                foreach ($occurrences as $occurrence) {
                    // The first occurrence that's higher than the current
                    // day of the month wins.
                    // If we advanced to the next month or year, the first
                    // occurrence is always correct.
                    if ($occurrence > $currentDayOfMonth || $advancedToNewMonth) {
                        // only consider byMonth matches,
                        // otherwise, we don't follow RRule correctly
                        if (in_array($currentMonth, $this->byMonth)) {
                            break 2;
                        }
                    }
                }

                // If we made it here, it means we need to advance to
                // the next month or year.
                $currentDayOfMonth = 1;
                $advancedToNewMonth = true;
                do {
                    ++$currentMonth;
                    if ($currentMonth > 12) {
                        $currentYear += $this->interval;
                        $currentMonth = 1;
                    }
                } while (!in_array($currentMonth, $this->byMonth));

                $this->currentDate = $this->currentDate->setDate(
                    (int) $currentYear,
                    (int) $currentMonth,
                    (int) $currentDayOfMonth
                );

                // To prevent running this forever (better: until we hit the max date of DateTimeImmutable) we simply
                // stop at 9999-12-31. Looks like the year 10000 problem is not solved in php ....
                if ($this->currentDate->getTimestamp() > self::dateUpperLimit) {
                    $this->currentDate = null;

                    return;
                }
            }

            // If we made it here, it means we got a valid occurrence
            $this->currentDate = $this->currentDate->setDate(
                (int) $currentYear,
                (int) $currentMonth,
                (int) $occurrence
            )->modify($this->startTime());

            return;
        } else {
            // These are the 'byMonth' rules, if there are no byDay or
            // byMonthDay sub-rules.
            do {
                ++$currentMonth;
                if ($currentMonth > 12) {
                    $currentYear += $this->interval;
                    $currentMonth = 1;
                }
            } while (!in_array($currentMonth, $this->byMonth));
            $this->currentDate = $this->currentDate->setDate(
                (int) $currentYear,
                (int) $currentMonth,
                (int) $currentDayOfMonth
            )->modify($this->startTime());

            return;
        }
    }

    /* }}} */

    /**
     * This method receives a string from an RRULE property, and populates this
     * class with all the values.
     *
     * @param string|array $rrule
     */
    protected function parseRRule($rrule)
    {
        if (is_string($rrule)) {
            $rrule = Property\ICalendar\Recur::stringToArray($rrule);
        }

        foreach ($rrule as $key => $value) {
            $key = strtoupper($key);
            switch ($key) {
                case 'FREQ':
                    $value = strtolower($value);
                    if (!in_array(
                        $value,
                        ['secondly', 'minutely', 'hourly', 'daily', 'weekly', 'monthly', 'yearly']
                    )) {
                        throw new InvalidDataException('Unknown value for FREQ='.strtoupper($value));
                    }
                    $this->frequency = $value;
                    break;

                case 'UNTIL':
                    $this->until = DateTimeParser::parse($value, $this->startDate->getTimezone());

                    // In some cases events are generated with an UNTIL=
                    // parameter before the actual start of the event.
                    //
                    // Not sure why this is happening. We assume that the
                    // intention was that the event only recurs once.
                    //
                    // So we are modifying the parameter so our code doesn't
                    // break.
                    if ($this->until < $this->startDate) {
                        $this->until = $this->startDate;
                    }
                    break;

                case 'INTERVAL':
                case 'COUNT':
                    $val = (int) $value;
                    if ($val < 1) {
                        throw new InvalidDataException(strtoupper($key).' in RRULE must be a positive integer!');
                    }
                    $key = strtolower($key);
                    $this->$key = $val;
                    break;

                case 'BYSECOND':
                    $this->bySecond = (array) $value;
                    break;

                case 'BYMINUTE':
                    $this->byMinute = (array) $value;
                    break;

                case 'BYHOUR':
                    $this->byHour = (array) $value;
                    break;

                case 'BYDAY':
                    $value = (array) $value;
                    foreach ($value as $part) {
                        if (!preg_match('#^  (-|\+)? ([1-5])? (MO|TU|WE|TH|FR|SA|SU) $# xi', $part)) {
                            throw new InvalidDataException('Invalid part in BYDAY clause: '.$part);
                        }
                    }
                    $this->byDay = $value;
                    break;

                case 'BYMONTHDAY':
                    $this->byMonthDay = (array) $value;
                    break;

                case 'BYYEARDAY':
                    $this->byYearDay = (array) $value;
                    foreach ($this->byYearDay as $byYearDay) {
                        if (!is_numeric($byYearDay) || (int) $byYearDay < -366 || 0 == (int) $byYearDay || (int) $byYearDay > 366) {
                            throw new InvalidDataException('BYYEARDAY in RRULE must have value(s) from 1 to 366, or -366 to -1!');
                        }
                    }
                    break;

                case 'BYWEEKNO':
                    $this->byWeekNo = (array) $value;
                    foreach ($this->byWeekNo as $byWeekNo) {
                        if (!is_numeric($byWeekNo) || (int) $byWeekNo < -53 || 0 == (int) $byWeekNo || (int) $byWeekNo > 53) {
                            throw new InvalidDataException('BYWEEKNO in RRULE must have value(s) from 1 to 53, or -53 to -1!');
                        }
                    }
                    break;

                case 'BYMONTH':
                    $this->byMonth = (array) $value;
                    foreach ($this->byMonth as $byMonth) {
                        if (!is_numeric($byMonth) || (int) $byMonth < 1 || (int) $byMonth > 12) {
                            throw new InvalidDataException('BYMONTH in RRULE must have value(s) between 1 and 12!');
                        }
                    }
                    break;

                case 'BYSETPOS':
                    $this->bySetPos = (array) $value;
                    break;

                case 'WKST':
                    $this->weekStart = strtoupper($value);
                    break;

                default:
                    throw new InvalidDataException('Not supported: '.strtoupper($key));
            }
        }
    }

    /**
     * Mappings between the day number and english day name.
     *
     * @var array
     */
    protected $dayNames = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Returns all the occurrences for a monthly frequency with a 'byDay' or
     * 'byMonthDay' expansion for the current month.
     *
     * The returned list is an array of integers with the day of month (1-31).
     *
     * @return array
     */
    protected function getMonthlyOccurrences()
    {
        $startDate = clone $this->currentDate;

        $byDayResults = [];

        // Our strategy is to simply go through the byDays, advance the date to
        // that point and add it to the results.
        if ($this->byDay) {
            foreach ($this->byDay as $day) {
                $dayName = $this->dayNames[$this->dayMap[substr($day, -2)]];

                // Dayname will be something like 'wednesday'. Now we need to find
                // all wednesdays in this month.
                $dayHits = [];

                // workaround for missing 'first day of the month' support in hhvm
                $checkDate = new \DateTime($startDate->format('Y-m-1'));
                // workaround modify always advancing the date even if the current day is a $dayName in hhvm
                if ($checkDate->format('l') !== $dayName) {
                    $checkDate = $checkDate->modify($dayName);
                }

                do {
                    $dayHits[] = $checkDate->format('j');
                    $checkDate = $checkDate->modify('next '.$dayName);
                } while ($checkDate->format('n') === $startDate->format('n'));

                // So now we have 'all wednesdays' for month. It is however
                // possible that the user only really wanted the 1st, 2nd or last
                // wednesday.
                if (strlen($day) > 2) {
                    $offset = (int) substr($day, 0, -2);

                    if ($offset > 0) {
                        // It is possible that the day does not exist, such as a
                        // 5th or 6th wednesday of the month.
                        if (isset($dayHits[$offset - 1])) {
                            $byDayResults[] = $dayHits[$offset - 1];
                        }
                    } else {
                        // if it was negative we count from the end of the array
                        // might not exist, fx. -5th tuesday
                        if (isset($dayHits[count($dayHits) + $offset])) {
                            $byDayResults[] = $dayHits[count($dayHits) + $offset];
                        }
                    }
                } else {
                    // There was no counter (first, second, last wednesdays), so we
                    // just need to add the all to the list).
                    $byDayResults = array_merge($byDayResults, $dayHits);
                }
            }
        }

        $byMonthDayResults = [];
        if ($this->byMonthDay) {
            foreach ($this->byMonthDay as $monthDay) {
                // Removing values that are out of range for this month
                if ($monthDay > $startDate->format('t') ||
                    $monthDay < 0 - $startDate->format('t')) {
                    continue;
                }
                if ($monthDay > 0) {
                    $byMonthDayResults[] = $monthDay;
                } else {
                    // Negative values
                    $byMonthDayResults[] = $startDate->format('t') + 1 + $monthDay;
                }
            }
        }

        // If there was just byDay or just byMonthDay, they just specify our
        // (almost) final list. If both were provided, then byDay limits the
        // list.
        if ($this->byMonthDay && $this->byDay) {
            $result = array_intersect($byMonthDayResults, $byDayResults);
        } elseif ($this->byMonthDay) {
            $result = $byMonthDayResults;
        } else {
            $result = $byDayResults;
        }
        $result = array_unique($result);
        sort($result, SORT_NUMERIC);

        // The last thing that needs checking is the BYSETPOS. If it's set, it
        // means only certain items in the set survive the filter.
        if (!$this->bySetPos) {
            return $result;
        }

        $filteredResult = [];
        foreach ($this->bySetPos as $setPos) {
            if ($setPos < 0) {
                $setPos = count($result) + ($setPos + 1);
            }
            if (isset($result[$setPos - 1])) {
                $filteredResult[] = $result[$setPos - 1];
            }
        }

        sort($filteredResult, SORT_NUMERIC);

        return $filteredResult;
    }

    /**
     * Simple mapping from iCalendar day names to day numbers.
     *
     * @var array
     */
    protected $dayMap = [
        'SU' => 0,
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
    ];

    protected function getHours()
    {
        $recurrenceHours = [];
        foreach ($this->byHour as $byHour) {
            $recurrenceHours[] = $byHour;
        }

        return $recurrenceHours;
    }

    protected function getDays()
    {
        $recurrenceDays = [];
        foreach ($this->byDay as $byDay) {
            // The day may be preceded with a positive (+n) or
            // negative (-n) integer. However, this does not make
            // sense in 'weekly' so we ignore it here.
            $recurrenceDays[] = $this->dayMap[substr($byDay, -2)];
        }

        return $recurrenceDays;
    }

    protected function getMonths()
    {
        $recurrenceMonths = [];
        foreach ($this->byMonth as $byMonth) {
            $recurrenceMonths[] = $byMonth;
        }

        return $recurrenceMonths;
    }
}
