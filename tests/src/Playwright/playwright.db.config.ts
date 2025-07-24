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
