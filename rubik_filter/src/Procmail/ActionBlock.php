<?php


namespace Rubik\Procmail;

use Rubik\Procmail\Constants\Action;

/**
 * Holder class for filter actions.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 * @see Filter
 * @see Action
 */
class ActionBlock
{
    /**
     * @var string[] subset of rule {@link Action} constants considered valid for filters
     * <ul>
     *  <li>{@link Action::MAILBOX}</li>
     *  <li>{@link Action::FWD}</li>
     *  <li>{@link Action::DISCARD}</li>
     *  <li>{@link Action::PIPE}</li>
     * </ul>
     */
    public const VALID_FILTER_ACTIONS = array(Action::MAILBOX, Action::FWD, Action::DISCARD, Action::PIPE);
    /** @var array {@link Action} => array(arguments...) */
    private $actions = array();

    /**
     * Add an action to this block.
     *
     * @param $action string one of {@link ActionBlock::VALID_FILTER_ACTIONS} constants
     * @param $arg string|null action argument (eg. mailbox name)
     * @return bool true on success or false on error
     */
    public function addAction($action, $arg)
    {
        if (array_search($action, self::VALID_FILTER_ACTIONS) === false) {
            return false;
        }

        if ($action === Action::DISCARD) {
            if (key_exists(Action::DISCARD, $this->actions)
                || !empty(array_diff(array_keys($this->actions), array(Action::PIPE)))) {
                // discard can only be present once and in conjunction with pipe actions
                return false;
            }
        } else { // fwd, mailbox or pipe
            if (key_exists(Action::DISCARD, $this->actions)) {
                // discard can only be the last action
                return false;
            }

            if ($action === Action::FWD) { // validate email
                $clean = filter_var($arg, FILTER_SANITIZE_EMAIL);

                if ($clean !== $arg || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }

                $arg = $clean;
            }
        }

        $this->actions[$action][] = $arg;

        return true;
    }

    /**
     * Remove given action if exists.
     *
     * @param $action string one of {@link ActionBlock::VALID_FILTER_ACTIONS}
     * @param $arg string|null action argument
     */
    public function removeAction($action, $arg) {
        if (!isset($this->actions[$action])) return;

        $index = array_search($arg, $this->actions[$action]);

        if ($index === false) return;

        unset ($this->actions[$action][$index]);
        $this->actions[$action] = array_values($this->actions[$action]);
    }

    /**
     * Clear all actions.
     */
    public function clearActions() {
        $this->actions = array();
    }

    /**
     * Preprocess and get actions.
     *
     * Resulting array values:
     * <ul>
     *  <li>{@link Action::FWD} => string - addresses separated by single space
     *  <li>{@link Action::DISCARD} => array(null)
     *  <li>{@link Action::PIPE} and {@link Action::MAILBOX} => array
     * </ul>
     *
     * @return array
     */
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
                case Action::PIPE:
                case Action::MAILBOX:
                    $actions[$key] = $action;
                    break;
            }
        }

        return $actions;
    }

    /**
     * Check if block contains no actions.
     *
     * @return bool
     */
    public function isEmpty() {
        return empty($this->actions);
    }
}