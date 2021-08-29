/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { notif } from './misc';
import i18next from 'i18next';

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('info').dataset.page !== 'edit') {
    return;
  }

  // store the clicks
  let clickX = [];
  let clickY = [];
  // bool to store the state of painting
  let isPainting;
  let wasPainting;

  const doodleCanvas = document.getElementById('doodleCanvas') as HTMLCanvasElement;
  const context: CanvasRenderingContext2D = doodleCanvas.getContext('2d');

  function draw(dragging: boolean): void {
    // get last items in arrays
    const x = clickX[clickX.length - 1];
    const y = clickY[clickY.length - 1];

    const path = new Path2D();

    if (dragging) {
      path.moveTo(clickX[clickX.length - 2], clickY[clickY.length - 2]);
    } else {
      // if it's just a point click, draw from close location
      path.moveTo(x - 1, y);
    }
    path.lineTo(x, y);
    path.closePath();

    context.globalCompositeOperation = 'source-over';
    context.strokeStyle = (document.getElementById('doodleStrokeStyle') as HTMLInputElement).value as string;
    if ((document.getElementById('doodleEraser') as HTMLInputElement).checked) {
      context.globalCompositeOperation = 'destination-out';
      context.strokeStyle = 'rgba(0,0,0,1)';
    }

    context.lineJoin = 'round';
    context.lineWidth = Number((document.getElementById('doodleStrokeWidth') as HTMLInputElement).value);

    context.stroke(path);
  }

  function addText(x: number, y: number, text: string): void {
    context.font = '18px Arial';
    context.fillStyle = (document.getElementById('doodleStrokeStyle') as HTMLInputElement).value as string;
    context.fillText(text, x, y);
  }

  function addClick(x: number, y: number, dragging: boolean): void {
    clickX.push(x);
    clickY.push(y);
    draw(dragging);
  }

  document.getElementById('clearCanvas').addEventListener('click', () => {
    context.clearRect(0, 0, context.canvas.width, context.canvas.height);
    clickX = [];
    clickY = [];
  });

  document.getElementById('saveCanvas').addEventListener('click', (e) => {
    const image = doodleCanvas.toDataURL();
    const elDataset = (e.target as HTMLButtonElement).dataset;
    let type = elDataset.type;
    const id = elDataset.id;
    const realName = prompt(i18next.t('request-filename'));
    if (realName == null) {
      return;
    }
    $.post('app/controllers/EntityAjaxController.php', {
      addFromString: true,
      type: type,
      realName: realName,
      id: id,
      fileType: 'png',
      string: image
    }).done(function(json) {
      if (type === 'items') {
        type = 'database';
      }
      $('#filesdiv').load(type + '.php?mode=edit&id=' + id + ' #filesdiv > *');
      notif(json);
    });
  });


  /**
   * mouse events
   */
  doodleCanvas.addEventListener('mousedown', (e) => {
    e.preventDefault();
    const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();

    // if ctrl key is pressed, we ask for text to insert
    if (e.ctrlKey) {
      const text = prompt('Text to insert:');
      if (text === null) {
        return;
      }
      addText(e.clientX - rect.left, e.clientY - rect.top, text);
    } else {
      isPainting = true;
      addClick(e.clientX - rect.left, e.clientY - rect.top, false);
    }
  });

  doodleCanvas.addEventListener('mousemove', (e) => {
    e.preventDefault();
    if (isPainting) {
      const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();
      addClick(e.clientX - rect.left, e.clientY - rect.top, true);
    }
  });

  doodleCanvas.addEventListener('mouseleave', (e) => {
    e.preventDefault();
    if (isPainting) {
      const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();
      addClick(e.clientX - rect.left, e.clientY - rect.top, true);
      isPainting = false;
      if (e.buttons !== 0) {
        wasPainting = true;
      }
    }
  });

  doodleCanvas.addEventListener('mouseenter', (e) => {
    e.preventDefault();
    if (e.buttons !== 0 && wasPainting) {
      isPainting = true;
      wasPainting = false;
      const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();
      addClick(e.clientX - rect.left, e.clientY - rect.top, false);
    }
  });

  doodleCanvas.addEventListener('mouseup', (e) => {
    e.preventDefault();
    isPainting = false;
    wasPainting = false;
  });

  /**
   * touch events
   */
  doodleCanvas.addEventListener('touchstart', (e) => {
    if (e.touches.length === 1) {
      e.preventDefault();
      const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();
      const touch = e.touches[0];
      isPainting = true;
      addClick(touch.clientX - rect.left, touch.clientY - rect.top, false);
    }
  }, false);

  doodleCanvas.addEventListener('touchmove', (e) => {
    if (isPainting) {
      e.preventDefault();
      const rect = (e.target as HTMLCanvasElement).getBoundingClientRect();
      const touch = e.touches[0];
      addClick(touch.clientX - rect.left, touch.clientY - rect.top, true);
    }
  }, false);

  doodleCanvas.addEventListener('touchend', (e) => {
    e.preventDefault();
    isPainting = false;
  }, false);

  doodleCanvas.addEventListener('touchcancel', () => {
    isPainting = false;
  }, false);
});
