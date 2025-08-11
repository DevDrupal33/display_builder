/*
 * Display Builder tests configuration.
 */
export default {
  operatingMode: "native",
  drushCmd: "drush",

  dbList: "admin/structure/display-builder/index",
  dbAddUrl: "admin/structure/display-builder/instance/add",
  dbViewUrl: "admin/structure/display-builder/instance/{db_id}",
  dbDeleteUrl: "admin/structure/display-builder/instance/{db_id}/delete",
  dbDeleteAllUrl: "admin/structure/display-builder/instance/delete-all",
  dbEditUrl: "admin/structure/display-builder/instance/{db_id}/edit",

  viewsList: "admin/structure/views",
  viewsAddUrl: "admin/structure/views/add",
  viewsEditUrl: "admin/structure/views/view/{view_id}/edit",
  viewsDbList: "admin/structure/views/display-builder",
  viewsTestName: "test_db_view", // Must match the view ID in the test config.

  pageListUrl: "admin/structure/page-layout",
  pageAddUrl: "admin/structure/page-layout/add",
  pageTestName: "page_layout__test",

  logInUrl: "user/login",
  logOutUrl: "user/logout",

  authDir: ".auth",

  pantheon: {
    isTarget: false,
    site: "aSite",
    environment: "dev",
  },

  targetSite: {
    isTarget: false,
    root: null, // optional
    remoteHost: "localhost",
    remoteUser: null, // optional
    sshOptions: "-p 2222", // optional
  },
}
