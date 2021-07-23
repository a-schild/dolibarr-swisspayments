<?php

namespace Z38\SwissPayment\Money;

/**
 * Sum of money in Israeli shekels
 */
class ILS extends Money
{
    /**
     * {@inheritdoc}
     */
    final public function getCurrency()
    {
        return 'ILS';
    }

    /**
     * {@inheritdoc}
     */
    final protected function getDecimals()
    {
        return 2;
    }
}
