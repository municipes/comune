<?php

namespace Drupal\rubrica\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rubrica\Helper\FullSearch;
use Drupal\rubrica\Helper\RicercaPersonaUo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Rubrica form.
 */
class SearchForm extends FormBase {

  /**
   * The search by field manager.
   *
   * @var \Drupal\rubrica\Helper\RicercaPersonaUo
   */
  protected $ricercaPersonaUo;

  /**
   * The search api manager.
   *
   * @var \Drupal\rubrica\Helper\FullSearch
   */
  protected $fullSearch;

  /**
   * {@inheritdoc}
   */
  public function __construct(RicercaPersonaUo $ricercaPersonaUo, FullSearch $fullSearch) {
    $this->ricercaPersonaUo = $ricercaPersonaUo;
    $this->fullSearch = $fullSearch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rubrica.ricercapersonauo'),
      $container->get('rubrica.fullsearch')
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

    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('I campi di ricerca sono alternativi: usa nome/cognome, oppure l\'ufficio, oppure la ricerca libera — non è possibile combinarli.'),
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome'),
      '#required' => FALSE,
      '#states' => [
        'enabled' => [
          ':input[name="office"]' => ['value' => '0'],
          ':input[name="fulltext"]' => ['value' => ''],
        ],
      ],
    ];
    $form['last_name'] = [
      '#type' => 'search',
      '#title' => $this->t('Cognome'),
      '#required' => FALSE,
      '#states' => [
        'enabled' => [
          ':input[name="office"]' => ['value' => '0'],
          ':input[name="fulltext"]' => ['value' => ''],
        ],
      ],
    ];
    $form['office'] = [
      '#type' => 'select',
      '#options' => $this->ricercaPersonaUo->getUo(),
      '#title' => $this->t('Ufficio'),
      '#required' => FALSE,
      '#states' => [
        'enabled' => [
          ':input[name="first_name"]' => ['value' => ''],
          ':input[name="last_name"]' => ['value' => ''],
          ':input[name="fulltext"]' => ['value' => ''],
        ],
      ],
    ];

    $form['fulltext'] = [
      '#type' => 'search',
      '#title' => $this->t('Ricerca libera'),
      '#required' => FALSE,
      '#states' => [
        'enabled' => [
          ':input[name="first_name"]' => ['value' => ''],
          ':input[name="last_name"]' => ['value' => ''],
          ':input[name="office"]' => ['value' => '0'],
        ],
      ],
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
    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('rubrica.search'),
      '#attributes' => ['class' => ['btn', 'btn-outline-danger']],
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
    $form['search_results']['messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    // The triggering element is the button that triggered the form submit. This
    // will be empty on initial page load, as the form has not been submitted
    // yet. Therefore the code inside the conditional is only executed when a
    // value has been submitted, and there are results to be rendered.
    if ($form_state->getTriggeringElement() && !$form_state->getErrors()) {
      // Get the text submitted by the user as a search query.
      $firstName = trim((string) $form_state->getValue('first_name'));
      $lastName = trim((string) $form_state->getValue('last_name'));
      $office = (int) $form_state->getValue('office');
      $fulltext = trim((string) $form_state->getValue('fulltext'));
      if (empty($fulltext)) {
        $result = $this->ricercaPersonaUo->searchByFields($firstName, $lastName, $office);
      }
      else {
        $result = $this->fullSearch->searchapiQuery($fulltext);
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $office = (int) $form_state->getValue('office');
    $hasName = trim((string) $form_state->getValue('first_name')) !== ''
      || trim((string) $form_state->getValue('last_name')) !== '';
    $hasFulltext = trim((string) $form_state->getValue('fulltext')) !== '';

    if ($office !== 0 && $hasName) {
      $form_state->setErrorByName('office', $this->t('Usa la ricerca per ufficio oppure per nome e cognome, non entrambe.'));
    }
    if ($hasFulltext && ($hasName || $office !== 0)) {
      $form_state->setErrorByName('fulltext', $this->t('La ricerca libera non è combinabile con gli altri campi.'));
    }
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
