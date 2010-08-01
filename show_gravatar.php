<?php

/**
 * Show gravatar for sender
 *
 * This plugin will show gravatar picture for message sender.
 *
 * Enable the plugin in config/main.inc.php
 *
 * @version 0.1
 * @author Ondra 'Kepi' Kudlik
 * @website http://kepi.cz/
 */

class show_gravatar extends rcube_plugin
{
  public $task = 'mail|settings';

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
  private $gravatar_url = 'http://www.gravatar.com/';

  function init()
  {
    $this->add_texts('localization/', false);

    $this->rcmail = rcmail::get_instance();

    echo $_SERVER['SCRIPT_URI'];
    if ( $this->is_https() ) {
      $this->gravatar_url = 'https://secure.gravatar.com/';
    }
    
    // preview
    if ($this->rcmail->task == 'mail'
          && ( ($this->enabled('gravatar_enable_message') && $this->rcmail->action == 'show')
            || ($this->enabled('gravatar_enable_preview') && $this->rcmail->action == 'preview'))) {

      $this->add_hook('render_page', array($this, 'render_page'));
      $this->add_hook('message_load', array($this, 'message_load'));
      $this->add_hook('template_object_messageheaders', array($this, 'html_output'));

      $this->size = $this->rcmail->config->get('gravatar_size', $this->default_size);
      $this->rating = $this->rcmail->config->get('gravatar_rating', $this->default_rating);
      $this->default =
        $this->rcmail->config->get('gravatar_default', $this->default_default);

      $this->border = $this->enabled('gravatar_border');

      $skin = $this->rcmail->config->get('skin');
      if (!file_exists($this->home."/skins/$skin/help.css"))
        $skin = 'default';
  
      // add style for placing gravatar icon
      $this->include_stylesheet("skins/$skin/show_gravatar.css");
    }
    else if ($this->rcmail->task == 'settings') {
      $dont_override = $this->rcmail->config->get('dont_override', array());
      if (!in_array('show_gravatar', $dont_override)) {
        $this->add_hook('user_preferences', array($this, 'prefs_table'));
        $this->add_hook('save_preferences', array($this, 'save_prefs'));
      }
    }

  }

  // returns true if rc is running on https protocol
  function is_https()
  {
    return ( $_SERVER["HTTPS"] == 'on' || $_SERVER["HTTP_FRONT_END_HTTPS"] == 'on' 
              || preg_match("/^https:/", $_SERVER['SCRIPT_URI']) ) ? true : false;
  }

  function enabled($option)
  {
    return $this->rcmail->config->get($option) ? true : false;
  }

  // helper for checkbox
  function checkbox($option, &$options)
  {
      $value = $this->rcmail->config->get($option);
      $checkbox = new html_checkbox(array('name' => '_'.$option, 'id' => $option, 'value' => 1));

      $options[$option] = array(
        'title' => html::label($option, Q($this->gettext($option))),
        'content' => $checkbox->show($value?1:0)
      );
  }

  // check if array is associative
  function is_assoc($array) {
        return (is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array)==0));
  }
  
  // helper for select
  function select($option, $possible_options, $default, &$options)
  {
      $value = $this->rcmail->config->get($option, $default);
      $select = new html_select(array('name' => '_'.$option));

      // if associative, build needed arrays
      if ( $this->is_assoc($possible_options) ) {

        $opt_labels = array();
        $opt_attrs = array();

        foreach($possible_options as $attr => $label) {
          $opt_labels[] = $label;
          $opt_attrs[] = (string)$attr;
        }

        $select->add($opt_labels, $opt_attrs);

      // else options and attrs are same
      } else {
        $select->add($possible_options, $possible_options);
      }


      $options[$option] = array(
        'title' => html::label($option, Q($this->gettext($option))),
        'content' => $select->show($value)
      );
  }

  function prefs_table($args)
  {
    $options = array();

    $this->checkbox('gravatar_enable_preview', $options);
    $this->checkbox('gravatar_enable_message', $options);

    $this->select('gravatar_size', array('16','24','32','48','64','128'), "{$this->default_size}", $options);
    $this->select('gravatar_rating', 
                    array('g' => Q($this->gettext('gravatar_G')),
                          'pg' => Q($this->gettext('gravatar_PG')),
                          'r' => Q($this->gettext('gravatar_R')),
                          'x' => Q($this->gettext('gravatar_X'))
                      ), $this->default_rating, $options);

    $this->select('gravatar_default', 
                    array('' => 'Blue G',
                          'identicon' => 'Identicon',
                          'monsterid' => 'Monsterid',
                          'wavatar' => 'Wavatar',
                          'mm' => 'Mistery-man',
                          '404' => Q($this->gettext('gravatar_none'))
                      ), $this->default_default, $options);

    $this->checkbox('gravatar_border', $options);

    if ($args['section'] == 'mailview') {

      $args['blocks']['gravatar'] = array(
        'name' => Q($this->gettext('gravatars')),
        'options' => $options
      );
    }

    return $args;
  }

  function save_prefs($args)
  {
    if ($args['section'] == 'mailview') {
      $args['prefs']['gravatar_enable_preview'] = get_input_value('_gravatar_enable_preview', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_enable_message'] = get_input_value('_gravatar_enable_message', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_size'] = get_input_value('_gravatar_size', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_rating'] = get_input_value('_gravatar_rating', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_default'] = get_input_value('_gravatar_default', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_border'] = get_input_value('_gravatar_border', RCUBE_INPUT_POST);
      return $args;
    }
  }

  function message_load($p)
  {
    $this->sender = (array)$p['object']->sender;
    $this->gravatar_id = md5(strtolower($this->sender['mailto']));
  }

  function gravatar()
  {
    $url = $this->gravatar_url . "avatar/" . $this->gravatar_id
      . "?s=" . $this->size
      . "&r=" . $this->rating
      . "&d=" . $this->default;
    return html::div(array('class' => 'gravatar'.($this->border?' gravatarBorder':'') ),
      html::img(array('src' => $url, 'title' => 'Gravatar')));
  }

  function html_output($p)
  {
    $p['content'] = $this->gravatar().$p['content'];

    return $p;
  } 

  function render_page($p)
  {
    $this->rcmail->output->add_header(
      html::tag('link', array('rel' => 'dns-prefetch',
                'href' => $this->gravatar_url)));
  }
}
