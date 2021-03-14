/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { Ajax } from './Ajax.class';
import { ResponseMsg } from './interfaces';
import Crud from './Crud.class';
import i18next from 'i18next';
import tinymce from 'tinymce/tinymce';
import { saveAs } from 'file-saver/dist/FileSaver.js';

export default class Template extends Crud {
  type: string;

  constructor() {
    super('app/controllers/Ajax.php');
    this.type = 'experiments_templates';
  }

  create(title: string, body = ''): void {
    this.send({
      action: 'create',
      type: this.type,
      what: 'template',
      params: {
        name: title,
        template: body,
      },
    }).then((response) => {
      window.location.replace(`ucp.php?tab=3&templateid=${response.value}`);
    });
  }

  saveToFile(id, name): void {
    // we have the name of the template used for filename
    // and we have the id of the editor to get the content from
    // we don't use activeEditor because it requires a click inside the editing area
    const content = tinymce.get('e' + id).getContent();
    const blob = new Blob([content], {type: 'text/plain;charset=utf-8'});
    saveAs(blob, name + '.elabftw.tpl');
  }

  duplicate(id: number): void {
    this.send({
      action: 'duplicate',
      what: 'template',
      type: 'experiments_templates',
      params: {
        itemId: id,
      },
    });
  }

  destroy(id: number): Promise<ResponseMsg>  {
    if (confirm(i18next.t('generic-delete-warning'))) {
      const AjaxC = new Ajax('experiments_templates', String(id));
      return AjaxC.post('destroy');
    }
    return Promise.reject(new Error('Action aborted'));
  }
}
