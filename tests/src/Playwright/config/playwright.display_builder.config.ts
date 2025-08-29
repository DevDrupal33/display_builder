/*
 * Display Builder tests configuration.
 */
export default {
  keyboardTimeout: 350, // Because keyboard.js has a 300 ms highlight of the clicked button.

  dbList: 'admin/structure/display-builder/instances',
  dbAddUrl: 'admin/structure/display-builder/instance/add',
  dbViewUrl: 'admin/structure/display-builder/instance/{db_id}',
  dbDeleteUrl: 'admin/structure/display-builder/instance/{db_id}/delete',
  dbDeleteAllUrl: 'admin/structure/display-builder/instance/delete-all',
  dbEditUrl: 'admin/structure/display-builder/instance/{db_id}/edit',

  viewsList: 'admin/structure/views',
  viewsAddUrl: 'admin/structure/views/add',
  viewsEditUrl: 'admin/structure/views/view/{view_id}/edit',
  viewsDbList: 'admin/structure/views/display-builder',
  viewsTestName: 'test_db_view', // Must match the view ID in the test config.

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',
  pageTestName: 'page_layout__test',
}
// 
