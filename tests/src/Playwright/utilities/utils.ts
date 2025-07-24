
/**
 * Return a string of random characters of specified length.
 *
 * @param {number} length
 *   Length of string to return.
 * @returns {string}
 *   The random string.
 */
export function createRandomString(length: number = 3): string {
  let result = '';
  const characters =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  const charactersLength = characters.length;

  for (let i = 0; i < length; i += 1) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }

  return result;
}
