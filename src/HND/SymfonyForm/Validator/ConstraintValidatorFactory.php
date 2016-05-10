<?php

namespace HND\SymfonyForm\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory as BaseConstraintValidatorFactory;

class ConstraintValidatorFactory extends BaseConstraintValidatorFactory
{
    protected $container;

    /**
     * @var array
     */
    protected $serviceNames;

    /**
     * Constructor.
     *
     * @param mixed $container    DI container
     * @param array     $serviceNames Validator service names
     */
    public function __construct($container, array $serviceNames = array(), $propertyAccessor = null)
    {
        parent::__construct($propertyAccessor);
        $this->container = $container;
        $this->serviceNames = $serviceNames;
    }
    /**
     * {@inheritdoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();
        if (isset($this->serviceNames[$name])) {
            return $this->container[$this->serviceNames[$name]];
        }
        return parent::getInstance($constraint);
    }
}
