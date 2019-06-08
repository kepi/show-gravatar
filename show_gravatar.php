<?php
/**
 * Show gravatar for sender
 *
 * This plugin will show gravatar picture for message sender.
 *
 * Copyright (C) 2009 Ondřej Kudlík https://kepi.cz
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * Enable the plugin in config/main.inc.php
 *
 * @version 0.3
 * @author Ondra Kudlík (Kepi)
 * @website https://kepi.cz/
 */

class show_gravatar extends rcube_plugin
{
    public $task = 'mail|settings|addressbook';

    private $gravatar_id;
    private $sender;
    private $rcmail;
    private $size;
    private $rating;
    private $default;
    private $border;
    private $default_size = 48;
    private $default_rating = 'g';
    private $default_default = 'identicon';
    private $gravatar_port = 80;
    private $gravatar_hostname = 'www.gravatar.com';
    private $gravatar_url = 'http://www.gravatar.com/';

    function init()
    {
        $this->add_texts('localization/', false);

        $this->rcmail = rcmail::get_instance();

        if ($this->is_https()) {
            $this->gravatar_port = 443;
            $this->gravatar_hostname = 'secure.gravatar.com';
            $this->gravatar_url = 'https://secure.gravatar.com/';
        }

        // use native support for contact photos
        if (
            $this->rcmail->task == 'addressbook' &&
            ($this->rcmail->action == 'photo' ||
                $this->rcmail->action == 'show')
        ) {
            $this->size = $this->rcmail->config->get(
                'gravatar_size',
                $this->default_size
            );
            $this->rating = $this->rcmail->config->get(
                'gravatar_rating',
                $this->default_rating
            );
            $this->default = '404';

            // use dedicated hook to show contact photos
            $this->add_hook('contact_photo', array($this, 'contact_photo'));
        } elseif ($this->rcmail->task == 'settings') {
            $dont_override = $this->rcmail->config->get(
                'dont_override',
                array()
            );
            if (!in_array('show_gravatar', $dont_override)) {
                $this->add_hook('preferences_list', array(
                    $this,
                    'prefs_table'
                ));
                $this->add_hook('preferences_save', array($this, 'save_prefs'));
            }
        }
    }

    // returns true if rc is running on https protocol
    function is_https()
    {
        return $_SERVER["HTTPS"] == 'on' ||
            $_SERVER["HTTP_FRONT_END_HTTPS"] == 'on' ||
            preg_match("/^https:/", $_SERVER['SCRIPT_URI'])
            ? true
            : false;
    }

    function enabled($option)
    {
        return $this->rcmail->config->get($option) ? true : false;
    }

    // check if array is associative
    function is_assoc($array)
    {
        return is_array($array) &&
            (0 !==
                count(array_diff_key($array, array_keys(array_keys($array)))) ||
                count($array) == 0);
    }

    // helper for select
    function select($option, $possible_options, $default, &$options)
    {
        $value = $this->rcmail->config->get($option, $default);
        $select = new html_select(array('name' => '_' . $option));

        // if associative, build needed arrays
        if ($this->is_assoc($possible_options)) {
            $opt_labels = array();
            $opt_attrs = array();

            foreach ($possible_options as $attr => $label) {
                $opt_labels[] = $label;
                $opt_attrs[] = (string) $attr;
            }

            $select->add($opt_labels, $opt_attrs);

            // else options and attrs are same
        } else {
            $select->add($possible_options, $possible_options);
        }

        $options[$option] = array(
            'title' => html::label($option, rcube::Q($this->gettext($option))),
            'content' => $select->show($value)
        );
    }

    function prefs_table($args)
    {
        $options = array();

        $this->select(
            'gravatar_size',
            array('16', '24', '32', '48', '64', '128'),
            "{$this->default_size}",
            $options
        );
        $this->select(
            'gravatar_rating',
            array(
                'g' => rcube::Q($this->gettext('gravatar_G')),
                'pg' => rcube::Q($this->gettext('gravatar_PG')),
                'r' => rcube::Q($this->gettext('gravatar_R')),
                'x' => rcube::Q($this->gettext('gravatar_X'))
            ),
            $this->default_rating,
            $options
        );

        if ($args['section'] == 'mailview') {
            $args['blocks']['gravatar'] = array(
                'name' => rcube::Q($this->gettext('gravatars')),
                'options' => $options
            );
        }

        return $args;
    }

    function save_prefs($args)
    {
        if ($args['section'] == 'mailview') {
            $args['prefs']['gravatar_size'] = rcube_utils::get_input_value(
                '_gravatar_size',
                RCUBE_INPUT_POST
            );
            $args['prefs']['gravatar_rating'] = rcube_utils::get_input_value(
                '_gravatar_rating',
                RCUBE_INPUT_POST
            );

            return $args;
        }
    }

    function contact_photo($p)
    {
        // if no contact photo was found
        if (!$p['data']) {
            // TODO: try for every email address of contact record?
            $emails = rcube_addressbook::get_col_values(
                'email',
                $p['record'],
                true
            );
            $email = $p['email'] ? $p['email'] : $emails[0];

            if ($email) {
                $this->gravatar_id = md5(strtolower($email));
                $url = $this->gravatar_url();

                $headers = get_headers($url);
                if (is_array($headers) && preg_match("/200 OK/", $headers[0])) {
                    $p['url'] = $url;
                }
            }
        }

        return $p;
    }


    function gravatar()
    {
        $url = $this->gravatar_url();

        // check if remote image doesn't return 404 if we use
        // 404 for default gravatar
        if ($this->default == '404') {
            $headers = get_headers($url);

            if (
                !is_array($headers) ||
                preg_match("/404 Not Found/", $headers[0])
            ) {
                return;
            }
        }

        return html::div(
            array(
                'class' => 'gravatar' . ($this->border ? ' gravatarBorder' : '')
            ),
            html::img(array('src' => $url, 'title' => 'Gravatar'))
        );
    }

    function gravatar_url()
    {
        return $this->gravatar_url .
            "avatar/" .
            $this->gravatar_id .
            "?s=" .
            $this->size .
            "&r=" .
            $this->rating .
            "&d=" .
            $this->default;
    }
}
