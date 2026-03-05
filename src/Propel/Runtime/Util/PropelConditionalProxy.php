<?php

declare(strict_types = 1);

namespace Propel\Runtime\Util;

use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Proxy for conditional statements in a fluid interface.
 * This class replaces another class for wrong statements,
 * and silently catches all calls to non-conditional method calls
 *
 * @example
 * <code>
 * $c->_if(true) // returns $c
 *     ->doStuff() // executed
 *   ->_else() // returns a PropelConditionalProxy instance
 *     ->doOtherStuff() // not executed
 *   ->_endif(); // returns $c
 * $c->_if(false) // returns a PropelConditionalProxy instance
 *     ->doStuff() // not executed
 *   ->_else() // returns $c
 *     ->doOtherStuff() // executed
 *   ->_endif(); // returns $c
 * @see Criteria
 * @template T of \Propel\Runtime\ActiveQuery\Criteria
 */
class PropelConditionalProxy
{
    /**
     * @var T
     */
    protected $criteria;

    /**
     * @var \Propel\Runtime\Util\PropelConditionalProxy<T>|null
     */
    protected $parent;

    /**
     * @var bool
     */
    protected $state;

    /**
     * @var bool
     */
    protected $wasTrue;

    /**
     * @var bool
     */
    protected $parentState;

    /**
     * @param T $criteria
     * @param mixed $cond
     * @param \Propel\Runtime\Util\PropelConditionalProxy<T>|null $proxy
     */
    public function __construct(Criteria $criteria, $cond, ?PropelConditionalProxy $proxy = null)
    {
        $this->criteria = $criteria;
        $this->wasTrue = false;
        $this->setConditionalState($cond);
        $this->parent = $proxy;

        $this->parentState = $proxy?->getConditionalState() ?? true;
    }

    /**
     * Returns a new level PropelConditionalProxy instance.
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @return T|\Propel\Runtime\Util\PropelConditionalProxy<T>
     */
    public function _if($cond)
    {
        return $this->criteria->_if($cond);
    }

    /**
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @return $this|T
     */
    public function _elseif($cond)
    {
        $cond = (bool)$cond; // Intentionally not typing the param to allow for evaluation inside this function

        return $this->setConditionalState(!$this->wasTrue && $cond);
    }

    /**
     * Allows for conditional statements in a fluid interface.
     *
     * @return $this|T
     */
    public function _else()
    {
        return $this->setConditionalState(!$this->state && !$this->wasTrue);
    }

    /**
     * Returns the parent object
     * Allows for conditional statements in a fluid interface.
     *
     * @return T|\Propel\Runtime\Util\PropelConditionalProxy<T>
     */
    public function _endif()
    {
        return $this->criteria->_endif();
    }

    /**
     * return the current conditional status
     *
     * @return bool
     */
    protected function getConditionalState(): bool
    {
        return $this->state && $this->parentState;
    }

    /**
     * @param mixed $cond
     *
     * @return $this|T
     */
    protected function setConditionalState($cond)
    {
        $this->state = (bool)$cond;
        $this->wasTrue = $this->wasTrue || $this->state;

        return $this->getCriteriaOrProxy();
    }

    /**
     * @return self<T>|null
     */
    public function getParentProxy(): ?self
    {
        return $this->parent;
    }

    /**
     * @return $this|T
     */
    public function getCriteriaOrProxy()
    {
        return $this->state && $this->parentState
            ? $this->criteria
            : $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return $this
     */
    public function __call(string $name, array $arguments)
    {
        return $this;
    }
}
