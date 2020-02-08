<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Action;

class FilterActionBlock
{
    private const VALID_FILTER_ACTIONS = array(Action::DISCARD, Action::MAILBOX, Action::FWD);
    private $actions = array();

    /**
     * @param $action string one of {@link Action} constants
     * @param $arg string|null
     * @return bool
     */
    public function addAction($action, $arg)
    {
        if (!array_search($action, self::VALID_FILTER_ACTIONS)) {
            return false;
        }

        if ($action === Action::DISCARD) {
            if (!empty($this->actions) && !key_exists($action, $this->actions)) {
                // discard can only be the only action
                return false;
            }
        } else { // fwd, or mailbox
            if (key_exists(Action::DISCARD, $this->actions)) {
                // discard can only be the only action
                return false;
            }
        }

        $this->actions[$action][] = $arg;

        return true;
    }

    public function clearActions() {
        $this->actions = array();
    }

    public function getActions() {
        $actions = array();

        foreach($this->actions as $key => $action) {
            switch ($key) {
                case Action::FWD:
                    // we can have all forwards in one line
                    $actions[Action::FWD] = array(implode(" ", $action));
                    break;
                case Action::DISCARD:
                    $actions[Action::DISCARD] = array(null);
                    break;
                case Action::MAILBOX:
                    // split into multiple mailbox actions
                    $actions[Action::MAILBOX] = $action;
                    break;
            }
        }

        return $actions;
    }

    public function isEmpty() {
        return empty($this->actions);
    }
}