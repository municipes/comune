<?php

namespace Drupal\rubrica\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rubrica\Helper\EntityQueries;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Rubrica form.
 */
class SearchForm extends FormBase {

  /**
   * The entity type manager
   *
   * @var \Drupal\rubrica\Helper\EntityQueries
   */
  protected $entityQueries;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityQueries $entityQueries) {
    $this->entityQueries = $entityQueries;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rubrica.entity_queries')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rubrica_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome'),
      '#required' => FALSE,
    ];
    $form['last_name'] = [
      '#type' => 'search',
      '#title' => $this->t('Cognome'),
      '#required' => FALSE,
    ];
    $form['office'] = [
      '#type' => 'select',
      '#options' => $this->entityQueries->getUO(),
      '#title' => $this->t('Ufficio'),
      '#required' => FALSE,
    ];

    $form['alternative'] = [
      '#type' => 'item',
      '#markup' => $this->t('O in alternativa puoi usare la ricerca libera.'),
    ];

    $form['fulltext'] = [
      '#type' => 'search',
      '#title' => $this->t('Ricerca libera'),
      '#required' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'set_search_results_wrapper',
      ],
    ];

    // The wrapper for search results.
    $form['search_results'] = [
      // Set the results to be below the form.
      '#weight' => 100,
      // The prefix/suffix are the div with the ID specified as the wrapper in
      // the submit button's #ajax definition.
      '#prefix' => '<div id="set_search_results_wrapper">',
      '#suffix' => '</div>',
      // The #markup element forces rendering of the #prefix and #suffix.
      // Without content, the wrappers are not rendered. Therefore, an empty
      // string is declared, ensuring that the wrapper for the search results
      // is present when the page is loaded.
      '#markup' => '',
    ];

    // The triggering element is the button that triggered the form submit. This
    // will be empty on initial page load, as the form has not been submitted
    // yet. Therefore the code inside the conditional is only executed when a
    // value has been submitted, and there are results to be rendered.
    if ($form_state->getTriggeringElement()) {
      // Get the text submitted by the user as a search query.
      $firstName = trim($form_state->getValue('first_name'));
      $lastName = trim($form_state->getValue('last_name'));
      $office = $form_state->getValue('office');
      $fulltext = trim($form_state->getValue('fulltext'));
      if (empty($fulltext)) {
        $result = $this->entityQueries->searchByFields($firstName, $lastName, $office);
      }
      else {
        $result = $this->entityQueries->searchapiQuery($fulltext);
      }

      if ($result) {
        $form['search_results']['result'] = $result['search_results']['result'];
      }

      // Check if no results were found.
      if (!isset($form['search_results']['result'])) {
        // Add a 'no results found' message.
        $form['search_results']['result'] = [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => $this->t('Sorry, no results found for this search'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set the form to rebuild. The submitted values are maintained in the
    // form state, and used to build the search results in the form definition.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Custom ajax submit handler for the form. Returns search results.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // Return the search results element of the form.
    return $form['search_results'];
  }
}
