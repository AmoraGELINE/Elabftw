/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

interface ActionReq {
  action: string;
  content?: string|number|object;
  id?: number;
  type?: string;
}

interface ResponseMsg {
  res: boolean;
  msg: string;
  color?: string;
}

export {
  ActionReq,
  ResponseMsg,
};
