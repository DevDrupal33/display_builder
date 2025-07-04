import fs from 'fs';
import YAML from 'yaml';

/**
 * Return a string of random characters of specified length.
 *
 * @param length        int   Length of string to return.
 * @returns
 */
function createRandomString(length) {
  let result = '';
  const characters =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  const charactersLength = characters.length;

  for (let i = 0; i < length; i += 1) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }
  return result;
}

/**
 * Return a user object with random name, email, and password.
 *
 * @return {{userRoles: *[], userPassword: string, userEmail: string, userName: string}}
 */
function createRandomUser() {
  const name1 = createRandomString(6);
  const name2 = createRandomString(6);
  return {
    userName: `${name1} ${name2}`,
    userEmail: `${name1.toLowerCase()}.${name2.toLowerCase()}@db.email`,
    userPassword: createRandomString(18),
    userRoles: [],
  };
}

/**
 * Read data from a YAML file located in data.
 *
 * @param filename {string}
 * @return {object}
 */
function readYAML(filename) {
  const file = fs.readFileSync(`../data/${filename}`, 'utf8');
  return YAML.parse(file);
}

/**
 * Get multi-level property from an object.
 * E.g. if object is {"foo":{"bar":"buzz"}} and key is "foo.bar",
 * "buzz" will be returned.
 * If key at some level does not exist, null is returned.
 *
 * @param object {*} Initial object.
 * @param key {string} Property path.
 * @return {*}
 */
function getProperty(object, key) {
  let result;
  result = object;
  // eslint-disable-next-line no-restricted-syntax
  for (const p of key.split('.')) {
    if (result === undefined) {
      return null;
    }
    result = result[p];
  }

  if (result === undefined) {
    return null;
  }
  return result;
}

export { createRandomString, createRandomUser, readYAML, getProperty };
