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

  function init()
  {
    $this->add_texts('localization/', false);

    $this->rcmail = rcmail::get_instance();
    
    // preview
    if ($this->rcmail->task == 'mail'
          && ( ($this->enabled('gravatar_enable_message') && $this->rcmail->action == 'show')
            || ($this->enabled('gravatar_enable_preview') && $this->rcmail->action == 'preview'))) {

      $this->add_hook('message_load', array($this, 'message_load'));
      $this->add_hook('template_object_messageheaders', array($this, 'html_output'));

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

  function enabled($option)
  {
    return $this->rcmail->config->get($option) ? true : false;
  }

  function checkbox($option, &$options)
  {
      $value = $this->rcmail->config->get($option);
      $checkbox = new html_checkbox(array('name' => '_'.$option, 'id' => $option, 'value' => 1));

      $options[$option] = array(
        'title' => html::label($option, Q($this->gettext($option))),
        'content' => $checkbox->show($value?1:0)
      );
  }

  function prefs_table($args)
  {
    $options = array();

    $this->checkbox('gravatar_enable_preview', $options);
    $this->checkbox('gravatar_enable_message', $options);

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
    $size = 64;

    $url = "http://www.gravatar.com/avatar.php?gravatar_id=" . $this->gravatar_id . "&size=" . $size;
    return html::div(array('class' => 'gravatar'), html::img(array('src' => $url, 'alt' => 'Gravatar')));
  }

  function html_output($p)
  {
    $p['content'] = $this->gravatar().$p['content'];

    return $p;
  } 
}
