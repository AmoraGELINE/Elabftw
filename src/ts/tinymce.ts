/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import tinymce from 'tinymce/tinymce';
import { Editor } from 'tinymce/tinymce';
import { DateTime } from 'luxon';
import 'tinymce/icons/default';
import 'tinymce/plugins/advlist';
import 'tinymce/plugins/anchor';
import 'tinymce/plugins/autosave';
import 'tinymce/plugins/charmap';
import 'tinymce/plugins/code';
import 'tinymce/plugins/codesample';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/hr';
import 'tinymce/plugins/image';
import 'tinymce/plugins/imagetools';
import 'tinymce/plugins/insertdatetime';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/pagebreak';
import 'tinymce/plugins/paste';
import 'tinymce/plugins/save';
import 'tinymce/plugins/searchreplace';
import 'tinymce/plugins/table';
import 'tinymce/plugins/template';
import 'tinymce/plugins/visualblocks';
import 'tinymce/plugins/visualchars';
import 'tinymce/themes/silver';
import 'tinymce/themes/mobile';
import '../js/tinymce-langs/ca_ES.js';
import '../js/tinymce-langs/de_DE.js';
import '../js/tinymce-langs/en_GB.js';
import '../js/tinymce-langs/es_ES.js';
import '../js/tinymce-langs/fr_FR.js';
import '../js/tinymce-langs/id_ID.js';
import '../js/tinymce-langs/it_IT.js';
import '../js/tinymce-langs/ja_JP.js';
import '../js/tinymce-langs/ko_KR.js';
import '../js/tinymce-langs/nl_BE.js';
import '../js/tinymce-langs/pl_PL.js';
import '../js/tinymce-langs/pt_BR.js';
import '../js/tinymce-langs/pt_PT.js';
import '../js/tinymce-langs/ru_RU.js';
import '../js/tinymce-langs/sk_SK.js';
import '../js/tinymce-langs/sl_SI.js';
import '../js/tinymce-langs/zh_CN.js';
import '../js/tinymce-plugins/mention/plugin.js';
import EntityClass from './Entity.class';
import { EntityType, Target } from './interfaces';
import { getEntity, reloadElement } from './misc';
import { Api } from './Apiv2.class';

const ApiC = new Api();
// AUTOSAVE
const doneTypingInterval = 7000;  // time in ms between end of typing and save

// called when you click the save button of tinymce
export function quickSave(): void {
  const entity = getEntity();
  const EntityC = new EntityClass(entity.type);
  EntityC.update(entity.id, Target.Body, tinymce.activeEditor.getContent()).catch(() => {
    // detect if the session timedout (Session expired error is thrown)
    // store the modifications in local storage to prevent any data loss
    localStorage.setItem('body', tinymce.activeEditor.getContent());
    localStorage.setItem('id', String(entity.id));
    localStorage.setItem('type', entity.type);
    localStorage.setItem('date', new Date().toLocaleString());
    // reload the page so user gets redirected to the login page
    location.reload();
  }).then(() => {
    // remove dirty state of editor
    tinymce.activeEditor.setDirty(false);
  });
}

function getNow(): DateTime {
  const locale = document.getElementById('user-prefs').dataset.jslang;
  return DateTime.now().setLocale(locale);
}

function getDatetime(): string {
  const useIso = document.getElementById('user-prefs').dataset.isodate;
  if (useIso === '1') {
    const fullDatetime = getNow().toISO({ includeOffset: false });
    // now we remove the milliseconds from that string
    // 2021-04-23T18:57:28.633  ->  2021-04-23T18:57:28
    return fullDatetime.slice(0, -4);
  }
  return getNow().toLocaleString(DateTime.DATETIME_MED_WITH_WEEKDAY);
}

// ctrl-shift-D will add the date in the tinymce editor
function addDatetimeOnCursor(): void {
  tinymce.activeEditor.execCommand('mceInsertContent', false, `${getDatetime()} `);
}

function isOverCharLimit(): boolean {
  const body = tinymce.get(0).getBody();
  const text = tinymce.trim(body.innerText || body.textContent);
  return text.length > 1000000;
}

// user finished typing, save work
function doneTyping(): void {
  if (isOverCharLimit()) {
    alert('Too many characters!!! Cannot save properly!!!');
    return;
  }
  quickSave();
}

// options for tinymce to pass to tinymce.init()
export function getTinymceBaseConfig(page: string): object {
  let plugins = 'anchor table searchreplace code fullscreen insertdatetime paste charmap lists advlist save image imagetools link pagebreak mention codesample hr template visualblocks visualchars';
  if (page !== 'admin') {
    plugins += ' autosave';
  }
  const entity = getEntity();

  return {
    selector: '.mceditable',
    browser_spellcheck: true,
    skin_url: 'app/css/tinymce',
    plugins: plugins,
    pagebreak_separator: '<div class="page-break"></div>',
    toolbar1: 'undo redo | styleselect fontsizeselect bold italic underline | alignleft aligncenter alignright alignjustify | superscript subscript | bullist numlist outdent indent | forecolor backcolor | charmap adddate | codesample | link | save',
    removed_menuitems: 'newdocument, image',
    image_caption: true,
    images_reuse_filename: false, // if set to true the src url gets a date appended
    images_upload_credentials: true,
    contextmenu: false,
    paste_data_images: Boolean(page === 'edit'),
    // use the preprocessing function on paste event to fix the bgcolor attribute from libreoffice into proper background-color style
    paste_preprocess: function(plugin, args) {
      args.content = args.content.replaceAll('bgcolor="', 'style="background-color:');
    },
    content_style: '.mce-content-body {font-size:10pt;}',
    codesample_languages: [
      {text: 'Bash', value: 'bash'},
      {text: 'C', value: 'c'},
      {text: 'C++', value: 'cpp'},
      {text: 'CSS', value: 'css'},
      {text: 'Fortran', value: 'fortran'},
      {text: 'Go', value: 'go'},
      {text: 'Java', value: 'java'},
      {text: 'JavaScript', value: 'javascript'},
      {text: 'Julia', value: 'julia'},
      {text: 'Latex', value: 'latex'},
      {text: 'Lua', value: 'lua'},
      {text: 'Makefile', value: 'makefile'},
      {text: 'Matlab', value: 'matlab'},
      {text: 'Perl', value: 'perl'},
      {text: 'Python', value: 'python'},
      {text: 'R', value: 'r'},
      {text: 'Ruby', value: 'ruby'},
    ],
    codesample_global_prismjs: true,
    language: document.getElementById('user-prefs').dataset.lang,
    charmap_append: [
      [0x2640, 'female sign'],
      [0x2642, 'male sign'],
      [0x25A1, 'white square'],
    ],
    height: '500',
    mentions: {
      // use # for autocompletion
      delimiter: '#',
      // get the source from json with get request
      source: function(query: string, process: (data) => void): void {
        // grab experiments and items
        const expjson = ApiC.getJson(`${EntityType.Experiment}?limit=100&q=${query}`);
        const itemjson = ApiC.getJson(`${EntityType.Item}?limit=100&q=${query}`);
        // and merge them into one
        Promise.all([expjson, itemjson]).then(values => {
          process(values[0].concat(values[1]));
        });
      },
      insert: function(selected): string {
        if (selected.type === 'items') {
          ApiC.post(`${entity.type}/${entity.id}/items_links/${selected.id}`).then(() => reloadElement('linksDiv'));
        }
        if (selected.type === 'experiments' && (entity.type === EntityType.Experiment || entity.type === EntityType.Item)) {
          ApiC.post(`${entity.type}/${entity.id}/experiments_links/${selected.id}`).then(() => reloadElement('linksExpDiv'));
        }
        return `<span><a href='${selected.page}.php?mode=view&id=${selected.id}'>${selected.category} - ${selected.title}</a></span>`;
      },
    },
    mobile: {
      theme: 'mobile',
      plugins: [ 'save', 'lists', 'link' ],
      toolbar: [ 'undo', 'redo', 'bold', 'italic', 'underline', 'bullist', 'numlist', 'link' ],
    },
    // keyboard shortcut to insert today's date at cursor in editor
    setup: (editor: Editor): void => {
      // holds the timer setTimeout function
      let typingTimer;
      // make the edges round
      editor.on('init', () => editor.getContainer().className += ' rounded');
      // add date+time button
      editor.ui.registry.addButton('adddate', {
        icon: 'insert-time',
        tooltip: 'Insert timestamp',
        onAction: function() {
          editor.insertContent(`${getDatetime()} `);
        },
      });
      // some shortcuts
      editor.addShortcut('ctrl+shift+d', 'add date/time at cursor', addDatetimeOnCursor);
      editor.addShortcut('ctrl+=', 'subscript', () => editor.execCommand('subscript'));
      editor.addShortcut('ctrl+shift+=', 'superscript', () => editor.execCommand('superscript'));

      // on edit page there is an autosave triggered
      if (page === 'edit' || page === 'ucp') {
        editor.on('keydown', () => clearTimeout(typingTimer));
        editor.on('keyup', () => {
          clearTimeout(typingTimer);
          typingTimer = setTimeout(doneTyping, doneTypingInterval);
        });
      }
    },
    style_formats_merge: true,
    style_formats: [
      {
        title: 'Image Left',
        selector: 'img',
        styles: {
          'float': 'left',
          'margin': '0 10px 0 10px',
        },
      }, {
        title: 'Image Right',
        selector: 'img',
        styles: {
          'float': 'right',
          'margin': '0 0 10px 10px',
        },
      },
    ],
  };
}
