<?php

namespace Drupal\wso2silfi\Service;

/**
 * Interface OAuth2ClientInterface.
 *
 * @package Drupal\wso2silfi\Service
 */
interface OAuth2ClientInterface {

  /**
   * Initialize an OAuth2Client object.
   *
   * @param array $params
   *   Associative array of the parameters that are needed
   *   by the different types of authorization flows.
   * @param string $id
   *   ID of the client. If not given, it will be generated
   *   from token_endpoint, client_id and auth_flow.
   */
  public function init($params = NULL, $id = NULL);

  /**
   * Clear the token data from the session.
   */
  public function clearToken();

  /**
   * Get and return an access token.
   *
   * If there is an existing token (stored in session), return that one. But if
   * the existing token is expired, get a new one from the authorization server.
   *
   * If the refresh_token has also expired and the auth_flow is 'server-side', a
   * redirection to the oauth2 server will be made, in order to re-authenticate.
   * However the redirection will be skipped if the parameter $redirect is
   * FALSE, and NULL will be returned as access_token.
   */
  public function getAccessToken($redirect = TRUE);

  /**
   * Save the information needed for redirection after getting the token.
   */
  public function setRedirect($state, $redirect = NULL);

  /**
   * Redirect to the original path.
   *
   * Redirects are registered with OAuth2\Client::setRedirect()
   * The redirect contains the url to go to and the parameters
   * to be sent to it.
   */
  public function redirect($clean = TRUE);

}
