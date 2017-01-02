<?php
namespace Search\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class SearchQuery extends AbstractPlugin
{

    /**
     * @param string $queryParamName
     * @param array $filters
     * @return string
     */
    public function __invoke($queryParamName, array $filters)
    {
        $query = $this->getController()->params()->fromQuery($queryParamName) ?: '';

        $query = strtr($query, array(
            ' and ' => ' ',
            ' or ' => ' ',
        ));

        foreach ($filters as $filterName => $searchParams) {

            $query .= $this->buildFilterSubQuery(
                $filterName,
                $searchParams['indexKey'],
                isset($searchParams['strict']) ? $searchParams['strict'] : null
            );
        }

        return $query;
    }

    /**
     * @param string $filterName
     * @param string $indexKey
     * @param bool $strict
     * @return string
     */
    protected function buildFilterSubQuery($filterName, $indexKey, $strict = true)
    {
        $filterValues = (array) $this->getController()->params()->fromQuery($filterName);

        if (!$this->filterHasAtLeastOneValue($filterValues)) {
            return '';
        }

        $subQuery = $strict ? ' +(' : ' (';

        foreach ($filterValues as $filterValue) {
            $subQuery .= $indexKey . ':"' . $filterValue . '" OR ';
        }

        return $subQuery . $indexKey . ':"All")';
    }

    /**
     * @param array $values
     * @return bool
     */
    protected function filterHasAtLeastOneValue(array $values)
    {
        return count(array_filter($values)) > 0;
    }

}
