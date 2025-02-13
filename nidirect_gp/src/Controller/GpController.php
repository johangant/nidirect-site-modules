<?php

namespace Drupal\nidirect_gp\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\nidirect_gp\Entity\GpInterface;

/**
 * Class GpController.
 *
 *  Returns responses for GP routes.
 */
class GpController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a GP  revision.
   *
   * @param int $gp_revision
   *   The GP  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($gp_revision) {
    $gp = $this->entityManager()->getStorage('gp')->loadRevision($gp_revision);
    $view_builder = $this->entityManager()->getViewBuilder('gp');

    return $view_builder->view($gp);
  }

  /**
   * Page title callback for a GP  revision.
   *
   * @param int $gp_revision
   *   The GP  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($gp_revision) {
    $gp = $this->entityManager()->getStorage('gp')->loadRevision($gp_revision);
    return $this->t('Revision of %title from %date', ['%title' => $gp->label(), '%date' => format_date($gp->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a GP .
   *
   * @param \Drupal\nidirect_gp\Entity\GpInterface $gp
   *   A GP  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(GpInterface $gp) {
    $account = $this->currentUser();
    $langcode = $gp->language()->getId();
    $langname = $gp->language()->getName();
    $languages = $gp->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $gp_storage = $this->entityManager()->getStorage('gp');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $gp->label()]) : $this->t('Revisions for %title', ['%title' => $gp->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all gp revisions") || $account->hasPermission('administer gp entities')));
    $delete_permission = (($account->hasPermission("delete all gp revisions") || $account->hasPermission('administer gp entities')));

    $rows = [];

    $vids = $gp_storage->revisionIds($gp);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\nidirect_gp\GpInterface $revision */
      $revision = $gp_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $gp->getRevisionId()) {
          $link = $this->l($date, new Url('entity.gp.revision', ['gp' => $gp->id(), 'gp_revision' => $vid]));
        }
        else {
          $link = $gp->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.gp.translation_revert', [
                'gp' => $gp->id(),
                'gp_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.gp.revision_revert', [
                'gp' => $gp->id(),
                'gp_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.gp.revision_delete', ['gp' => $gp->id(), 'gp_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['gp_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
