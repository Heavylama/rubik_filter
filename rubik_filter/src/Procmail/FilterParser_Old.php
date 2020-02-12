<?php


namespace Rubik\Procmail;


use ArrayIterator;
use PHPUnit\Symfony\Polyfill\Ctype\Ctype;

class FilterParser_Old
{
    private const START_FILTER_REGEX = "^".FilterBuilder::FILTER_START . "(.*)$";
    private const END_FILTER_REGEX = "^".FilterBuilder::FILTER_END."(.*)$";

    /**
     * @param $procmail string
     * @return null
     */
    public function parse($procmail) {
        if (!is_string($procmail)) {
            return null;
        }

        $parsedFilters = array();

        $procmail = str_split(trim($procmail));

        $charsIterator = new ArrayIterator($procmail);

        while ($this->skipWhitespace($charsIterator)) {
            $filter = $this->readFilter($charsIterator, $parsedFilters);

            if ($filter === null) {
                return null;
            } else {
                $parsedFilters[] = $filter;
            }
        }

        return $parsedFilters;
    }

    /**
     * @param $it ArrayIterator
     */
    private function readFilter(&$it, &$filterArr) {
        $line = $this->readLine($it);

        if ($line === null) {
            return null;
        }

        $filterStart = $this->getFilterStart($line);

        if ($filterStart === null) {
            return null;
        }
//
//        $filter = new FilterBuilder();
//        $filter->setName($filterStart[1]);

        $rules = array();

        while ($it->valid()) {
            $rule = $this->readRule($it);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

    }

    private function readRule(&$it) {

    }

    /**
     * @param $line
     * @return array|null
     */
    private function getFilterStart($line) {
        $matches = array();

        if(preg_match(self::START_FILTER_REGEX, $line, $matches) !== false) {
            return $matches;
        } else {
            return null;
        }
    }

    /**
     * @param $it ArrayIterator
     * @return string|null
     */
    private function readLine(&$it) {
        if (!$it->valid()) {
            return null;
        }

        $line = '';

        while ($it->valid() && $it->current() !== "\n") {
            $line .= $it->current();
            $it->next();
        }

        $it->next();

        return $line;
    }

    /**
     * @param $it ArrayIterator
     * @return bool whether there are any characters left
     */
    private function skipWhitespace(&$it) {
        while ($it->valid() && ctype_space($it->current())) {
            $it->next();
        }

        return $it->valid();
    }

}