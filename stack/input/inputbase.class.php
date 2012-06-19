<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(__FILE__) . '/../../locallib.php');
require_once(dirname(__FILE__) . '/../options.class.php');
require_once(dirname(__FILE__) . '/../cas/casstring.class.php');
require_once(dirname(__FILE__) . '/../cas/cassession.class.php');
require_once(dirname(__FILE__) . '/inputstate.class.php');


/**
 * The base class for inputs in Stack.
 *
 * Inputs are the controls that the teacher can put into the question
 * text to receive the student's response.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class stack_input {
    const BLANK = '';
    const VALID = 'valid';
    const INVALID = 'invalid';
    const SCORE = 'score';

    protected static $perameterstack_inputsavailable = array(
        'mustVerify',
        'hideFeedback',
        'boxWidth',
        'boxHeight',
        'strictSyntax',
        'insertStars',
        'syntaxHint',
        'forbidWords',
        'forbidFloats',
        'lowestTerms',
        'sameType');

    /**
     * @var string the name of the input.
     * This name has two functions
     *  (1) it is the name of thename of the POST variable that the input from this
     *  element will be submitted as.
     *  (2) it is the name of the CAS variable to which the student's answer is assigned.
     *  Note, that during authoring, the teacher simply types #name# in the question stem to
     *  create these inputs.
     */
    protected $name;

    /**
     * @var string Every input must have a non-empty "teacher's answer".
     * This is assumed to be a valid Maxima string.
     */
    protected $teacheranswer;

    /**
     * Answertest paramaters.
     * @var array paramer name => current value.
     */
    protected $parameters;

    /**
     * Constructor
     *
     * @param string $name the name of the input. This is the name of the
     *      POST variable that the input from this element will be submitted as.
     * @param int $width size of the input.
     * @param string $default initial contets of the input.
     * @param int $maxLength limit on the maximum input length.
     * @param int $height height of the input.
     * @param array $parameters The options for this input. All the opitions have default
     *      values, so you only have to give options that are different from the default.
     */
    public function __construct($name, $teacheranswer, $parameters = null) {
        $this->name = $name;
        $this->teacheranswer = $teacheranswer;
        $class = get_class($this);
        $this->parameters = $class::get_parameters_defaults();

        if (!(null===$parameters || is_array($parameters))) {
            throw new stack_exception('stack_input: __construct: 3rd argumenr, $parameters, ' .
                    'must be null or an array of parameters.');
        }

        if (is_array($parameters)) {
            foreach ($parameters as $name => $value) {
                $this->set_parameter($name, $value);
            }
        }
    }

    /**
     * @param string $param a settings parameter name.
     * @return bool whether this input type uses this parameter.
     */
    public function is_parameter_used($param) {
        $class = get_class($this);
        return array_key_exists($param, $class::get_parameters_defaults());
    }

    /**
     * Sets the value of an input parameter.
     * @return array of parameters names.
     */
    public function set_parameter($parameter, $value) {
        if (!$this->is_parameter_used($parameter)) {
            throw new stack_exception('stack_input: setting parameter ' . $parameter .
                    ' which does not exist for inputs of type ' . get_class($this));
        }

        if ($parameter == 'hideFeedback' && $value && $this->is_parameter_used('mustVerify')) {
            $this->set_parameter('mustVerify', false);
        }

        $this->parameters[$parameter] = $value;
    }

    /**
     * Get the value of one of the parameters.
     * @param string $parameter the parameter name
     * @param mixed $default the default to return if this parameter is not set.
     */
    protected function get_parameter($parameter, $default = null) {
        if (array_key_exists($parameter, $this->parameters)) {
            return $this->parameters[$parameter];
        } else {
            return $default;
        }
    }

    /**
     * Validates the value of an input parameter.
     * @return array of parameters names.
     */
    public function validate_parameter($parameter, $value) {
        if (!$this->is_parameter_used($parameter)) {
            throw new stack_exception('stack_input: trying to validate parameter ' . $parameter .
                    ' which does not exist for inputs of type ' . get_class($this));
        }

        switch($parameter) {
            case 'mustVerify':
                $valid = is_bool($value);
                break;

            case 'hideFeedback':
                $valid = is_bool($value);
                break;

            case 'strictSyntax':
                $valid = is_bool($value);
                break;

            case 'insertStars':
                $valid = is_bool($value);
                break;

            case 'forbidFloats':
                $valid = is_bool($value);
                break;

            case 'lowestTerms':
                $valid = is_bool($value);
                break;

            case 'sameType':
                $valid = is_bool($value);
                break;

            default:
                $valid = $this->internal_validate_parameter($parameter, $value);
        }
        return $valid;
    }

    /**
     * Each actual extension of this base class must decide what parameter values are valid
     * @return array of parameters names.
     */
    public function internal_validate_parameter($parameter, $value) {
        return true;
    }

    /**
     * Returns a list of the names of all the parameters.
     * @return array of parameters names.
     */
    public function get_parameters_available() {
        return $this->perameterstack_inputsavailable;
    }

    /**
     * Return the default values for the parameters.
     * @return array parameters => default value.
     */
    public static function get_parameters_defaults() {
        return array();
    }

    /**
     * @return string the teacher's answer, an example of what could be typed into
     * this input as part of a correct response to the question.
     */
    public function get_teacher_answer() {
        return $this->teacheranswer;
    }

    /**
     * Validate any attempts at this question.
     *
     * @param array $response the student reponse to the question.
     * @param stack_options $options CAS options to use when validating.
     * @param string $teacheranswer the teachers answer as a string representation of the evaluated expression.
     * @return stack_input_state represents the current state of the input.
     */
    public function validate_student_response($response, $options, $teacheranswer, $forbiddenkeys) {

        if (!is_a($options, 'stack_options')) {
            throw new stack_exception('stack_input: validate_student_response: options not of class stack_options');
        }
        $localoptions = clone $options;

        if (array_key_exists($this->name, $response)) {
            $sans = $response[$this->name];
        } else {
            $sans = '';
        }

        if (array_key_exists($this->name . '_val', $response)) {
            $validator = $response[$this->name . '_val'];
        } else {
            $validator = '';
        }

        if ('' === $sans or false === $sans) {
            return new stack_input_state(self::BLANK, '', '', '', '');
        }
        $transformedanswer = $this->raw_input_to_maxima($sans);

        $answer = new stack_cas_casstring($transformedanswer);
        $answer->validate('s', $this->get_parameter('strictSyntax', true), $this->get_parameter('insertStars', false));

        // Ensure student hasn't used a variable name used by the teacher.
        if ($forbiddenkeys) {
            $answer->check_external_forbidden_words($forbiddenkeys);
        }

        $forbiddenwords = $this->get_parameter('forbidWords', '');
        if ($forbiddenwords) {
            $answer->check_external_forbidden_words(explode(',', $forbiddenwords));
        }

        $valid = $answer->get_valid();
        $errors = $answer->get_errors();
        // If we can't get a "displayed value" back from the CAS, show the student their original expression.
        $display = stack_maxima_format_casstring($sans);
        $interpretedanswer = $answer->get_casstring();

        // Send the string to the CAS.
        if ($valid) {
            if (!$this->get_parameter('sameType')) {
                $teacheranswer = null;
            }
            $answer->set_cas_validation_casstring($this->name,
                    $this->get_parameter('forbidFloats', false), $this->get_parameter('lowestTerms', false),
                    $teacheranswer);
            $localoptions->set_option('simplify', false);

            $session = new stack_cas_session(array($answer), $localoptions, 0);
            $session->instantiate();
            $session = $session->get_session();
            $answer = $session[0];

            $errors = stack_maxima_translate($answer->get_errors());
            if ('' != $errors) {
                $valid = false;
            }
            if ('' == $answer->get_value()) {
                $valid = false;
            } else {
                $display = '\[ ' . $answer->get_display() . ' \]';
            }
        }

        // Answers may not contain the ? character.  CAS-strings may, but answers may not.
        // It is very useful for teachers to be able to add in syntax hints.
        if (!(strpos($transformedanswer, '?') === false)) {
            $valid = false;
            $errors .= stack_string('qm_error');
        }

        if (!$valid) {
            $status = self::INVALID;
        } else if ($this->get_parameter('mustVerify', true) && $validator != $sans) {
            $status = self::VALID;
        } else {
            $status = self::SCORE;
        }
        return new stack_input_state($status, $sans, $interpretedanswer, $display, $errors);
    }

    public function requires_validation() {
        return $this->get_parameter('mustVerify', true);
    }

    /**
     * Returns the XHTML for embedding this input in a page.
     *
     * @param string student's current answer to insert into the xhtml.
     * @param string $fieldname the field name to use in the HTML for this input.
     * @param bool $readonly whether the contro should be displayed read-only.
     * @param string the value of the teacher's answer, post randomization.  This is needed, e.g. to automatically size the matrix elements.
     * @return string HTML for this input.
     */
    public abstract function render(stack_input_state $state, $fieldname, $readonly, $teachersanswer);

    /**
     * Add this input the MoodleForm, but only used in questiontestform.php.
     * It enables the teacher to enter the data as a CAS variable where necessary
     * when the student might get some html page formatting help.  E.g. teachers
     * will want to enter information into textareas input as a single list, or
     * variable name representing a list, and matrix elements as a single CAS
     * variable, or using Maxima's syntax matrix([...]).
     * @param MoodleQuickForm $mform the form to add elements to.
     */
    public abstract function add_to_moodleform_testinput(MoodleQuickForm $mform);

    /**
     * Generate the HTML that gives the results of validating the student's input.
     * @param stack_input_state $state represents the results of the validation.
     * @param string $fieldname the field name to use in the HTML for this input.
     * @return string HTML for the validation results for this input.
     */
    public function render_validation(stack_input_state $state, $fieldname) {
        if (self::BLANK == $state->status) {
            return '';
        }

        if ($this->get_parameter('hideFeedback', false) && self::INVALID != $state->status) {
            return '';
        }

        $feedback  = '';
        $feedback .= html_writer::tag('p', stack_string('studentValidation_yourLastAnswer') . $state->contentsdisplayed);

        if ($this->requires_validation() && '' !== $state->contents) {
            $feedback .= html_writer::empty_tag('input', array('type' => 'hidden',
                    'name' => $fieldname . '_val', 'value' => $state->contents));
        }

        if (self::INVALID == $state->status) {
            $feedback .= html_writer::tag('p', stack_string('studentValidation_invalidAnswer'));
        }

        if ($state->errors) {
            $feedback .= html_writer::tag('p', $state->errors, array('class' => 'stack_errors'));
        }
        return $feedback;
    }

    /**
     * Transforms the student's input into a casstring if needed.
     * Most return the same as went in.
     *
     * @param array|string $in
     * @return string
     */
    protected function raw_input_to_maxima($in) {
        return $in;
    }

    /**
     * Transforms a Maxima expression into a raw input which is placed in the html form.
     * Most return the same as went in.
     *
     * @param array|string $in
     * @return string
     */
    public function maxima_to_raw_input($in) {
        return $in;
    }

}
