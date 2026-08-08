import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// The Tree panel is a sidebar island (`tree`). It gets its own profile rather
// than being switched on in a shared one: the start sidebar then holds two
// islands, and whichever is not active leaves the other's content in the DOM
// but not visible - which silently breaks library drags for every test using
// that profile. Its job is to mirror the Builder's structure, so the test
// builds a nested component/slot/block in the canvas and asserts the tree
// reflects that nesting - not just that the rows exist.
//
// Rows are targeted on data-menu-type (component/slot/block) and the slot
// identity attributes, both stamped by TreePanel::renderComponent().
test('Navigator panel', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const tree = page.locator('.db-island-tree')
  const slot = tree.locator('[data-menu-type="slot"][data-slot-id="slot_1"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileTreeId)
  })

  // Built before opening the Tree: both share the start sidebar, and opening
  // the library for a drag would switch the drawer away from the tree.
  await test.step(`Build a nested structure`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-island-builder > div.db-dropzone').first(),
      { x: 40, y: 15 },
    )
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      page.locator('.db-island-builder [data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )
  })

  await test.step(`Open the Tree panel`, async () => {
    await page.getByRole('button', { name: 'Navigator' }).click()
    await displayBuilder.shoelaceReady()
    await expect(tree).toBeVisible()
  })

  await test.step(`Tree mirrors the builder structure`, async () => {
    await expect(tree.locator('[data-menu-type="component"]')).toHaveCount(1)
    await expect(slot).toHaveCount(1)
    await expect(tree.locator('[data-menu-type="block"][data-node-type="textfield"]')).toHaveCount(1)

    // The block must be nested inside the component's slot, not a sibling of it.
    await expect(slot.locator('[data-menu-type="block"][data-node-type="textfield"]')).toHaveCount(1)
  })
})

// The Navigator reuses the dropzone component, so its rows are draggable - but
// it is isolated into its own SortableJS group (@see
// components/dropzone/dropzone.js): a drop is accepted only from a list sharing
// its group name, so nothing drops in from the Canvas/Scaffold/library and its
// rows can't be dragged out, while moves *within* the tree still work. This
// guards that a re-parent performed entirely inside the tree reaches the
// backend (the Canvas mirrors it) and survives a reload - not just a visual
// DOM shuffle. Structural locators only, never node IDs: an HTMX swap after a
// drop can hand back a different data-node-id than the one read a moment ago.
test('Move within the Navigator', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const tree = page.locator('.db-island-tree')
  // Root-level component rows are the direct children of the tree's root
  // dropzone; nested ones live inside a slot below.
  const rootComponents = tree.locator('.db-dropzone--root > [data-menu-type="component"]')
  const allComponents = tree.locator('[data-menu-type="component"]')
  // A component nested inside the first root component's slot_1.
  const nested = rootComponents.first().locator('[data-slot-id="slot_1"] [data-menu-type="component"]')
  // The Canvas mirrors the same source tree - used to prove the move re-parented
  // the real node, not just a tree row.
  const canvasRootComponents = page.locator('.db-island-builder > div.db-dropzone > [data-test="test_simple"]')
  const canvasNested = canvasRootComponents
    .first()
    .locator('[data-slot-id="slot_1"] [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileTreeId)
  })

  // Built on the Canvas: the library cannot drop into the tree (that isolation
  // is exactly what this test relies on), so the two siblings are placed on the
  // Canvas and then moved relative to each other inside the tree.
  await test.step(`Build two sibling components on the Canvas`, async () => {
    const root = page.locator('.db-island-builder > div.db-dropzone').first()
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
  })

  await test.step(`Open the Navigator`, async () => {
    await page.getByRole('button', { name: 'Navigator' }).click()
    await displayBuilder.shoelaceReady()
    await expect(tree).toBeVisible()
    await expect(rootComponents).toHaveCount(2)
  })

  await test.step(`Move the second row into the first row's slot, inside the tree`, async () => {
    await displayBuilder.dragElement(
      rootComponents.nth(1),
      rootComponents.first().locator('[data-slot-id="slot_1"] .db-dropzone').first(),
      { x: 40, y: 15 },
    )

    // Re-parented within the tree: one component left at root, the other nested.
    await expect(rootComponents).toHaveCount(1)
    await expect(nested).toHaveCount(1)
    await expect(allComponents).toHaveCount(2)
  })

  await test.step(`The Canvas reflects the same re-parent`, async () => {
    await page.getByRole('tab', { name: 'Canvas' }).click()
    await expect(canvasRootComponents).toHaveCount(1)
    await expect(canvasNested).toHaveCount(1)
  })

  await test.step(`The move persisted (survives a reload)`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await page.getByRole('button', { name: 'Navigator' }).click()
    await displayBuilder.shoelaceReady()
    await expect(rootComponents).toHaveCount(1)
    await expect(nested).toHaveCount(1)
  })
})

// The Navigator remembers the single global "Collapse all" state (per builder,
// in localStorage - @see components/panel_tree/panel_tree.js) and reapplies it
// whenever the tree is re-rendered. The trap this guards: a Canvas move
// re-renders only the moved node's subtree via a partial out-of-band swap
// (TreePanel::replaceNode()), not the whole tree, so a reapply keyed on the
// tree root would miss it and the swapped-in subtree - a fresh server render,
// always expanded - would reopen. Per-node reapply keeps it collapsed.
test('Navigator keeps Collapse all through a Canvas move', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const tree = page.locator('.db-island-tree')
  const rootComponents = tree.locator('.db-dropzone--root > [data-menu-type="component"]')
  // Any expanded node at all - after "Collapse all" this must stay at zero,
  // including across the partial swap a move produces.
  const expandedNodes = tree.locator('.db-tree-node[data-expanded="true"]')
  const canvasRoot = page.locator('.db-island-builder > div.db-dropzone > [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileTreeId)
  })

  await test.step(`Build two components on the Canvas`, async () => {
    const root = page.locator('.db-island-builder > div.db-dropzone').first()
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
  })

  await test.step(`Open the Navigator and collapse everything`, async () => {
    await page.getByRole('button', { name: 'Navigator' }).click()
    await displayBuilder.shoelaceReady()
    await expect(rootComponents).toHaveCount(2)
    await page.locator('[data-tree-collapse-all]').click()
    await expect(expandedNodes).toHaveCount(0)
  })

  await test.step(`A Canvas move partial-swaps the tree, which stays collapsed`, async () => {
    // Re-parent on the Canvas: the second root component into the first's slot.
    // This is the replaceNode() path that used to hand the tree back expanded.
    await displayBuilder.dragElement(
      canvasRoot.nth(1),
      canvasRoot.first().locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )

    // The tree mirrored the move (so it really was re-rendered)...
    await expect(rootComponents).toHaveCount(1)
    // ...and nothing reopened: the freshly swapped-in subtree is still collapsed.
    await expect(expandedNodes).toHaveCount(0)
  })
})
