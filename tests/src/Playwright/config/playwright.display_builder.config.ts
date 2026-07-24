/*
 * Display Builder tests configuration.
 */
export default {
  dbList: 'admin/structure/display-builder/instances',

  viewsDbList: 'admin/structure/views/display-builder',

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',
  pageViewUrl: 'admin/structure/page-layout/{instance_id}/builder',

  // PageLayout::getPrefix()
  pagePrefix: 'page_layout__',
  // EntityViewDisplay::getPrefix()
  entityPrefix: 'entity_view__',
  // DisplayExtender::getPrefix()
  viewsPrefix: 'views__',

  keyExpand: 'Shift+E',

  startDrawerID: '#db-first-drawer',
  endDrawerID: '#db-second-drawer',

  // @see labels values in tests/modules/display_builder_test/config/install/display_builder.profile.*.yml
  testProfileFullId: 'test_base',
  testProfileFull: 'Test full',
  testProfileBuilderId: 'test_builder',
  testProfileBuilder: 'Test builder',
  testProfileScaffoldId: 'test_scaffold',
  testProfileScaffold: 'Test scaffold',
  testProfileTreeId: 'test_tree',
  testProfileTree: 'Test tree',
  testProfileCollaborationId: 'test_collaboration',
  testProfileCollaboration: 'Test collaboration',
  testProfileExtraId: 'test_extra',
  testProfileExtra: 'Test extra',
  testProfileMin: 'Test min',
  testProfileMinId: 'test_min',
}
