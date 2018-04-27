<?php

namespace Drupal\openy_map\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\openy_map\OpenyMapManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin settings Form for openy_map form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The OpenY Map manager.
   *
   * @var \Drupal\openy_map\OpenyMapManager
   */
  protected $openyMapManager;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\openy_map\OpenyMapManager $openy_map_manager
   *   The OpenY Map manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              OpenyMapManager $openy_map_manager) {
    parent::__construct($config_factory);
    $this->openyMapManager = $openy_map_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('openy_map.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openy_map_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'openy_map.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('openy_map.settings');
    $form_state->setCached(FALSE);

    $form['title'] = [
      '#markup' => '<h2>' . $this->t('Location list page settings') . '</h2>',
    ];

    $nodeTypes = $this->openyMapManager->getLocationNodeTypes();
    if (!empty($nodeTypes)) {
      // Render icon files from Location Finder module and default theme.
      $themeConfig = $this->config('system.theme');
      $themePath = drupal_get_path('theme', $themeConfig->get('default')) . '/img/locations_icons';
      $themeFiles = array_values(array_diff(scandir($themePath), ['.', '..']));
      $fileOptions = [];
      foreach ($themeFiles as $themeFile) {
        $path = '/' . $themePath . '/' . $themeFile;
        $fileOptions[$path] = '<img src="' . $path . '" />';
      }
      $locationFinderPath = drupal_get_path('module', 'location_finder') . '/img';
      $locationFinderFiles = array_values(array_diff(scandir($locationFinderPath), ['.', '..']));
      foreach ($locationFinderFiles as $locFile) {
        if (!in_array($locFile, $themeFiles)) {
          $path = '/' . $locationFinderPath . '/' . $locFile;
          $fileOptions[$path] = '<img src="' . $path . '" />';
        }
      }

      /** @var \Drupal\node\Entity\NodeType $nodeType */
      foreach ($nodeTypes as $nodeType) {
        $id = $nodeType->id();
        $label = $nodeType->label();

        $form[$id] = [
          '#type' => 'details',
          '#title' => $this->t('@branch content type', ['@branch' => $label]),
          '#open' => TRUE,
        ];
        $form[$id][$id . '_label'] = [
          '#type' => 'textfield',
          '#title' => t('Label to show on Locations list page:'),
          '#default_value' => !empty($config->get('type_labels')[$id]) ? $config->get('type_labels')[$id] : $label,
        ];
        $form[$id][$id . '_active'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable on map and list on Locations page', ['@branch' => $label]),
          '#default_value' => !empty($config->get('active_types')[$id]),
        ];
        $form[$id][$id . '_default'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Set as default filter values for Locations map', ['@branch' => $label]),
          '#default_value' => !empty($config->get('default_tags')[$id]),
        ];
        $form[$id][$id . '_icon'] = [
          '#prefix' => '<div class="container-inline">',
          '#type' => 'radios',
          '#title' => 'Locations Map icon',
          '#default_value' =>  $config->get('type_icons')[$id],
          '#options' => $fileOptions,
          '#description' => $this->t('Choose content type map icon. 
             To redefine icons add file in <b>{default_theme}/img/location_icons</b> directory in active default theme'),
          '#required' => TRUE,
          '#multiple' => FALSE,
          '#suffix' => '</div>',
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var $config \Drupal\Core\Config\Config */
    $config = $this->config('openy_map.settings');

    $nodeTypes = $this->openyMapManager->getLocationNodeTypes();
    $default_tags = $active_types = $type_labels = $type_icons = [];
    /** @var \Drupal\node\Entity\NodeType $nodeType */
    foreach ($nodeTypes as $nodeType) {
      $id = $nodeType->id();
      $label = !empty($form_state->getValue($id . '_label')) ?
        $form_state->getValue($id . '_label') : $nodeType->label();

      $default_tags[$id] = !empty($form_state->getValue($id . '_default')) ? $label : '';
      $active_types[$id] = !empty($form_state->getValue($id . '_active')) ? $label : '';
      $type_labels[$id] = $label;
      $type_icons[$id] = $form_state->getValue($id . '_icon');
    }

    $config->set('default_tags', $default_tags);
    $config->set('active_types', $active_types);
    $config->set('type_labels', $type_labels);
    $config->set('type_icons', $type_icons);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
