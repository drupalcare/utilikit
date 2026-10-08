<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ckeditor\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The toolbar panel, used the way an editor uses it, in a real browser.
 *
 * WHY THIS TEST EXISTS. The kernel tests prove the editor is SENT the right
 * data. None of them would have noticed what was measured on sts.local on
 * 2026-10-08, on the panel this one replaced: changing padding from 16px to
 * 24px left both classes on the paragraph; "text-align: banana" was written
 * without complaint; and the paragraph the button had just styled turned
 * invisible inside the editor, because UtiliKit's engine stylesheet hides
 * every unprocessed `.utilikit` element in inline mode. Each of those is a
 * step below that would go red again.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class UtilikitPanelTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'ckeditor5',
    'node',
    'text',
    'field',
    'utilikit',
    'utilikit_ckeditor',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // No filter_html: the format behaves like Full HTML, where the
    // maintainer reported the panel as confusing.
    FilterFormat::create([
      'format' => 'utilikit_full',
      'name' => 'UtiliKit full',
      'filters' => [],
    ])->save();
    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'utilikit_full',
      'image_upload' => ['status' => FALSE],
      'settings' => [
        'toolbar' => ['items' => ['utilikit']],
        'plugins' => [],
      ],
    ])->save();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    // A body field of our own: Drupal 12 no longer adds one to a new type.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Text',
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent('field_text', ['type' => 'text_textarea'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'create page content',
      'use text format utilikit_full',
    ]));
  }

  /**
   * Add, change, refuse, and keep the block visible.
   */
  public function testThePanelAsAnEditorUsesIt(): void {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('node/add/page');
    $this->waitForEditor();
    // A paragraph with the caret in it.
    $this->getSession()->executeScript(<<<'JS'
      const editor = [...Drupal.CKEditor5Instances.values()][0];
      editor.setData('<p>Styled paragraph.</p>');
      editor.editing.view.focus();
      editor.model.change((w) => w.setSelection(editor.model.document.getRoot().getChild(0), 'in'));
      JS);

    $this->getEditorButton('UtiliKit classes')->click();
    $panel = $session->waitForElementVisible('css', '.ck-utilikit-panel');
    $this->assertNotNull($panel, 'The panel opens.');

    // Grouped, and in plain words first.
    $utility = $this->control('utility');
    $this->assertGreaterThan(1, count($utility->findAll('css', 'optgroup')), 'Utilities are grouped.');
    $this->assertNotNull($utility->find('xpath', ".//option[@value='xw' and text()='Max width (xw)']"));
    // Every label is tied to a control that exists.
    foreach ($panel->findAll('css', 'label') as $label) {
      $for = (string) $label->getAttribute('for');
      $this->assertNotSame('', $for, $label->getText() . ' has no for.');
      $this->assertNotNull($page->findById($for), $label->getText() . ' points at nothing.');
    }
    // CKEditor drops empty attribute values; the default options must keep
    // theirs, or "All screen sizes" is submitted as a breakpoint.
    $this->assertCount(0, $panel->findAll('css', 'option:not([value])'));

    // Add padding.
    $this->choose('pd', '16px');
    $this->apply();
    $this->assertStringContainsString('class="utilikit uk-pd--16px"', $this->getEditorDataAsHtmlString());
    $this->assertNotNull($session->waitForElementVisible('css', '.ck-utilikit-panel__chip-label[title="uk-pd--16px"]'));

    // Change it: the new value REPLACES the old one.
    $this->choose('pd', '24px');
    $this->apply();
    $html = $this->getEditorDataAsHtmlString();
    $this->assertStringContainsString('uk-pd--24px', $html);
    $this->assertStringNotContainsString('uk-pd--16px', $html, 'Changing a value replaced it.');

    // A keyword utility is a list of its accepted words, nothing typed.
    $utility->selectOption('ta');
    $keyword = $this->control('keyword');
    $this->assertTrue($keyword->isVisible(), 'text-align offers its words as a list.');
    $this->assertFalse($this->control('value')->isVisible(), 'and no free-text field.');
    $keyword->selectOption('center');
    $this->control('screen')->selectOption('md');
    $this->apply();
    $this->assertStringContainsString('uk-md-ta--center', $this->getEditorDataAsHtmlString());

    // A value that is not a class is refused, not written.
    $this->choose('pd', '16px uk-evil');
    $this->apply();
    $this->assertStringNotContainsString('uk-evil', $this->getEditorDataAsHtmlString());
    $this->assertStringContainsString('cannot be used', $panel->find('css', '.ck-utilikit-panel__message')->getText());

    // Enter in the value field applies inside the panel; it must not submit
    // the node form the panel sits in.
    $url = $this->getSession()->getCurrentUrl();
    $this->choose('mg', '8px');
    $this->getSession()->executeScript("document.querySelector('.ck-utilikit-panel').requestSubmit();");
    $this->assertNotNull($session->waitForElement('css', '.ck-utilikit-panel__chip-label[title="uk-mg--8px"]'), 'Submitting the panel applied the class.');
    $this->assertStringContainsString('uk-mg--8px', $this->getEditorDataAsHtmlString());
    $this->assertSame($url, $this->getSession()->getCurrentUrl(), 'The node form was not submitted.');
    $this->assertTrue($panel->isVisible(), 'The panel stays open after applying.');

    // Removing one chip removes that class only.
    $panel->find('xpath', ".//li[button[@title='uk-mg--8px']]/button[contains(@class, 'chip-remove')]")->click();
    $this->assertStringNotContainsString('uk-mg--8px', $this->getEditorDataAsHtmlString());
    $this->assertStringContainsString('uk-pd--24px', $this->getEditorDataAsHtmlString());

    // The styled paragraph is still visible in the editor. The control first:
    // without the engine stylesheet on the page nothing would hide it, and
    // "visible" would prove nothing.
    $this->assertTrue($this->getSession()->evaluateScript(
      "[...document.styleSheets].some((s) => /utilikit\\.engine\\.css/.test(s.href || ''))"
    ), 'The engine stylesheet that hides unprocessed elements is on the page.');
    $visibility = $this->getSession()->evaluateScript(
      "getComputedStyle(document.querySelector('.ck-editor__editable p.utilikit')).visibility"
    );
    $this->assertSame('visible', $visibility, 'The block the panel styled is visible while editing.');

    // Nor does it carry the engine's endless loading shimmer, which slid past
    // the editable's edge and kept widening its scrollbar. The control first:
    // the shimmer is drawn only in inline mode.
    $this->assertTrue($this->getSession()->evaluateScript(
      "document.body.classList.contains('utilikit-inline-mode')"
    ), 'The page is in inline mode, where the engine draws its loading shimmer.');
    $before = $this->getSession()->evaluateScript(
      "getComputedStyle(document.querySelector('.ck-editor__editable p.utilikit'), '::before').display"
    );
    $this->assertSame('none', $before, 'The styled block shows no loading shimmer while editing.');

    // This user may not open the reference page, and the help and playground
    // submodules are off: no footer, rather than links to a 403 or a 404.
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "document.querySelectorAll('.ck-utilikit-panel__footer a').length"
    ), 'No help links, in any panel, for a user who can open none of them.');
  }

  /**
   * A short window and a crowded block: the panel stays on screen.
   */
  public function testThePanelFitsTheWindow(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'create page content',
      'use text format utilikit_full',
      'access utilikit reference',
    ]));
    $this->getSession()->resizeWindow(1200, 560);
    $this->drupalGet('node/add/page');
    $this->waitForEditor();
    $this->getSession()->executeScript(<<<'JS'
      const editor = [...Drupal.CKEditor5Instances.values()][0];
      // Room on the page to scroll the toolbar to mid-window.
      editor.ui.view.element.style.marginBlockStart = '600px';
      document.body.style.paddingBlockEnd = '1200px';
      editor.setData('<p>Crowded paragraph.</p>');
      editor.editing.view.focus();
      editor.model.change((w) => w.setSelection(editor.model.document.getRoot().getChild(0), 'in'));
      editor.execute('utilikit', {
        marker: 'utilikit',
        classes: 'uk-pd--16px uk-mg--b-12 uk-fs--18 uk-fw--600 uk-tc--212529 uk-bg--eef6ff uk-md-ta--center uk-lg-pd--t-32px uk-bc--3b82f6-50-h uk-bw--1px uk-br--8px uk-xw--640px uk-lh--1.5 uk-op--90 uk-md-mg--x-auto uk-zi--10',
      });
      JS);

    // The button at mid-window, where neither side has room for the whole
    // panel — measured on sts.local, the case that hid Apply off-screen.
    // Opened by a script click so the driver does not scroll it elsewhere.
    $this->getSession()->executeScript(<<<'JS'
      const button = [...document.querySelectorAll('.ck-dropdown__button')].find((b) => b.textContent.includes('UtiliKit'));
      const top = button.getBoundingClientRect().top + window.scrollY;
      window.scrollTo(0, top - window.innerHeight / 2);
      button.click();
      JS);
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '.ck-dropdown__panel-visible .ck-utilikit-panel'));
    $m = $this->getSession()->evaluateScript(<<<'JS'
      (() => {
        const p = document.querySelector('.ck-dropdown__panel-visible .ck-utilikit-panel');
        const chips = p.querySelector('.ck-utilikit-panel__chips');
        return {
          innerHeight: window.innerHeight,
          panelTop: p.getBoundingClientRect().top,
          panelBottom: p.getBoundingClientRect().bottom,
          applyBottom: p.querySelector('.ck-utilikit-panel__apply').getBoundingClientRect().bottom,
          bodyScroll: p.querySelector('.ck-utilikit-panel__body').scrollHeight,
          bodyBox: p.querySelector('.ck-utilikit-panel__body').clientHeight,
          chipsScroll: chips.scrollHeight,
          chipsBox: chips.clientHeight,
          count: p.querySelector('.ck-utilikit-panel__count').textContent,
        };
      })()
      JS);

    // The control: the body is scrolling, so the content is taller than the
    // room it was given and "it fits" is the cap at work, not luck.
    $this->assertLessThanOrEqual(560, $m['innerHeight'], 'The window really is short.');
    $this->assertGreaterThan($m['bodyBox'], $m['bodyScroll'], 'The panel body scrolls inside the cap.');
    $this->assertGreaterThanOrEqual(0, $m['panelTop'], 'The panel starts inside the window.');
    $this->assertLessThanOrEqual($m['innerHeight'], $m['panelBottom'], 'The panel ends inside the window.');
    $this->assertLessThanOrEqual($m['innerHeight'], $m['applyBottom'], 'Apply is on screen.');
    $this->assertGreaterThan($m['chipsBox'], $m['chipsScroll'], 'Sixteen chips scroll inside their own box.');
    $this->assertSame(' (16)', $m['count']);

    $links = $this->footerLinks();
    $this->assertCount(1, $links, 'Only the page this user can open is linked.');
    $this->assertSame('Reference', $links[0]['text']);
    $this->assertStringEndsWith('/utilikit/reference', $links[0]['href']);
    $this->assertSame('_blank', $links[0]['target'], 'Help opens in a new tab, never over the edit.');
  }

  /**
   * The panel footer's links, as text, href and target.
   *
   * @return list<array{text: string, href: string, target: string}>
   *   One entry per link.
   */
  private function footerLinks(): array {
    return $this->getSession()->evaluateScript(<<<'JS'
      [...document.querySelectorAll('.ck-dropdown__panel-visible .ck-utilikit-panel__footer a')].map((a) => ({
        text: a.textContent, href: a.getAttribute('href'), target: a.target,
      }))
      JS);
  }

  /**
   * Finds a panel control by its class suffix.
   */
  private function control(string $name): NodeElement {
    $element = $this->getSession()->getPage()->find('css', '.ck-utilikit-panel__' . $name);
    $this->assertNotNull($element, $name . ' control exists.');
    return $element;
  }

  /**
   * Picks a utility for all screen sizes and types a value.
   *
   * The screen size is set explicitly: the panel keeps the last one chosen
   * when the utility changes, as a person setting several styles for one
   * screen size would want.
   */
  private function choose(string $prefix, string $value): void {
    $this->control('utility')->selectOption($prefix);
    $this->control('screen')->selectOption('');
    $this->control('value')->setValue($value);
  }

  /**
   * Presses Apply.
   */
  private function apply(): void {
    $message = "document.querySelector('.ck-utilikit-panel__message')";
    $this->getSession()->executeScript($message . ".textContent = '';");
    $this->control('apply')->click();
    // Every Apply ends by saying what it did, so a message means it ran.
    $this->assertTrue($this->getSession()->wait(5000, $message . ".textContent !== ''"), 'Apply reported back.');
  }

}
