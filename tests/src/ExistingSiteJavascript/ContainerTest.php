<?php

namespace Drupal\Tests\acquia_cms\ExistingSiteJavascript;

/**
 * Tests "Background container" component.
 *
 * @group acquia_cms
 * @group site_studio
 * @group low_risk
 * @group pr
 * @group push
 */
class ContainerTest extends CohesionComponentTestBase {

  /**
   * Tests that the component can be added to a layout canvas.
   */
  public function testComponent() {
    $account = $this->createUser();
    $account->addRole('administrator');
    $account->save();
    $this->drupalLogin($account);

    // Create a random image that we can select in the media library when
    // editing the component.
    $this->createMedia(['bundle' => 'image']);

    $this->drupalGet('/node/add/page');

    // Add the component to the layout canvas.
    // @todo this need to be removed once ACO fixes ACO-2372.
    /*$edit_form = $this->getLayoutCanvas()->add('Container')->edit();
    $this->openMediaLibrary($edit_form, 'Select image');
    $this->selectMediaSource("Media Types");
    $this->selectMedia(0);
    $this->insertSelectedMedia();*/
  }

  /**
   * Tests that component can be edited by a specific user role.
   *
   * @param string $role
   *   The ID of the user role to test with.
   *
   * @dataProvider providerEditAccess
   */
  public function testEditAccess(string $role) {
    $account = $this->createUser();
    $account->addRole($role);
    $account->save();
    $this->drupalLogin($account);

    $this->drupalGet('/admin/cohesion/components/components');
    $this->editDefinition('Layout components', 'Container');
  }

}
