<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Rule;

class FilterParser
{
    public const RULES_REGEX =
          "/"
         ."^\s*:0(?'flags'[a-zA-z]*):(?'lockfile'\S*)\n"
         ."(?'conds'(?:^\*.*\n)*)^(?:(?:\s*{\s*\n"
         ."(?'sub_rule_action'(?:.*\n)*?)\s*})|(?'action'.*))$"
         ."/m";

    public const FILTER_REGEX =
          "/"
         ."^#START:(?'filter_start'.*)\n"
         ."(?'filter_content'(.*\n)*?)"
         ."#END:(?'filter_end'.*)$"
         ."/m";

    public function parse($input)
    {
        // Get
        $matches = $this->matchAll(self::FILTER_REGEX, $input);
        if ($matches === null) {
            return null;
        }

        $filters = array();

        foreach ($matches as $filterMatch) {
            $filterContent = $filterMatch['filter_content'][0];

            $parsedFilter = $this->parseFilter($filterContent);

            if ($parsedFilter === null) {
                return null;
            } else {
                $filters[] = $parsedFilter;
            }
        }

        return $filters;
    }

    /**
     * @param $filterContent string
     * @return null
     */
    private function parseFilter($filterContent) {
        $filterContent = trim($filterContent);

        $filter = new FilterBuilder();

        // check if filter is enabled, all lines are commented out using # otherwise
        $isEnabled = $this->isEnabled($filterContent);
        if ($isEnabled === null) {
            return null;
        } else if (!$isEnabled) {
            // all lines commented out => uncomment
            $filterContent = substr(str_replace("\n#", "\n", $filterContent), 1);
        }

        $filter->setFilterEnabled($isEnabled);


        // extract individual rules forming one filter using regex
        $matches = $this->matchAll(self::RULES_REGEX, $filterContent);
        if ($matches === null) {
            return null;
        }

        // extract action common for all rules in a filter
        $filterAction = $this->parseAction($matches[0]);
        if ($filterAction === null || $filterAction->isEmpty()) {
            return null;
        }
        $filter->setActionBlock($filterAction);


        return $filter;
    }


    /**
     * @param $rule array
     * @param FilterActionBlock|null $actionBlock
     * @return FilterActionBlock|null
     */
    private function parseAction($rule, &$actionBlock = null) {
        if ($rule['action'][0] !== null) {
            $action = trim($rule['action'][0]);

            if ($actionBlock === null) {
                $actionBlock = new FilterActionBlock();
            }

            if ($action === Rule::DISCARD_ACTION_ARG) { // discard to /dev/null
                if(!$actionBlock->addAction(Action::DISCARD, null)) {
                    return null;
                }
            } else if ($action[0] === "!") { // forward action
                $forwardEmails = explode(" ", trim(substr($action, 1)));

                foreach ($forwardEmails as $email) {
                    if (empty($email)) continue;

                    if(!$actionBlock->addAction(Action::FWD, $email)) {
                        return null;
                    }
                }
            } else { // mailbox name
                if(!$actionBlock->addAction(Action::MAILBOX, $action)) {
                    return null;
                }
            }

            return $actionBlock;
        } else if ($rule['sub_rule_action'] !== null) {
            $matches = $this->matchAll(self::RULES_REGEX, trim($rule['sub_rule_action']));
            if ($matches === null) {
                return null;
            }

            foreach ($matches as $rule) {
                if ($this->parseAction($rule, $actionBlock) === null) {
                    return null;
                }
            }

            return $actionBlock;
        } else {
            return null;
        }
    }

    private function isEnabled($filterContent) {
        $filterContent = trim($filterContent);

        $commented = $filterContent[0] === '#';

        $lines = explode("\n", $filterContent);

        foreach ($lines as $line) {
            $lineCommented = $line[0] === '#';

            if ($lineCommented !== $commented) {
                return null;
            }
        }

        return !$commented;
    }

    private function matchAll($regex, $input) {
        $input = trim($input);

        $match = preg_match_all($regex, $input, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE|PREG_UNMATCHED_AS_NULL);

        if ($match === FALSE || $match === 0) {
            return null;
        }

        if ($match > 0) {
            $lastMatch = $matches[count($matches) - 1];
            $lastMatchEnd = $lastMatch[0][1] + strlen($lastMatch[0][0]);

            // check if all input was consumed
            if ($lastMatchEnd != strlen($input)) {
                return null;
            }
        }

        return $matches;
    }
}