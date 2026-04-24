<?php
    /**
     * phpcs:ignoreFile
     *
     * Expected variables:
     * 
     * @var Propel\Generator\Model\Column $column
     * @var string $sql
     * @var array<string> $bindings
     * 
     */
?>

/**
 * Computes the value of the aggregate column <?= $column->getName()?>
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 *
 * @return mixed The scalar result from the aggregate query
 */
public function compute<?=$column->getPhpName()?>(ConnectionInterface $con)
{
    $stmt = $con->prepare('<?= $sql ?>');
<?php foreach ($bindings as $key => $binding):?>
    $stmt->bindValue(':p<?= $key ?>', $this->get<?= $binding ?>());
<?php endforeach;?>
    $stmt->execute();

    return $stmt->fetchColumn();
}
