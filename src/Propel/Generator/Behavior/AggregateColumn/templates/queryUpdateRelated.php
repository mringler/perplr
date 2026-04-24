<?php
    /**
     * phpcs:ignoreFile
     *
     * Expected variables:
     *
     * @var string $relationName
     * @var string $aggregateName
     * @var string $updateMethodName
     * @var string $refRelationName
     * @var string $variableName
     */
?>

/**
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 *
 * @return void
 */
protected function updateRelated<?=$relationName . $aggregateName?>s($con)
{
    if ($this-><?=$variableName?>s === null) {
        return;
    }

    foreach ($this-><?=$variableName?>s as $<?=$variableName?>) {
        $<?=$variableName?>-><?= $updateMethodName ?>($con);
    }
    $this-><?=$variableName?>s = null;
}
