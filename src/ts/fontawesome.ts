/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 *
 * In this file we define which icons we import (tree shaking feature) from font-awesome
 * This way we don't import all the icons \o/
 */

// CORE
import { library, dom } from '@fortawesome/fontawesome-svg-core';

// SOLID
import {
  faArrowUp,
  faArrowUpRightFromSquare,
  faArrowsDownToLine,
  faBold,
  faBoxArchive,
  faBug,
  faCalendarPlus,
  faChartPie,
  faCheck,
  faCheckSquare,
  faChevronCircleDown,
  faChevronCircleLeft,
  faChevronCircleRight,
  faChevronDown,
  faChevronLeft,
  faChevronRight,
  faClipboardCheck,
  faClipboardList,
  faClone,
  faCloud,
  faCode,
  faCogs,
  faComments,
  faCubes,
  faDna,
  faDownload,
  faEllipsisV,
  faEnvelope,
  faExclamationTriangle,
  faExternalLinkSquareAlt,
  faEye,
  faEyeSlash,
  faFile,
  faFileArchive,
  faFileCode,
  faFileExcel,
  faFileImage,
  faFileImport,
  faFilePdf,
  faFilePowerpoint,
  faFileVideo,
  faFileWord,
  faFingerprint,
  faHdd,
  faHeading,
  faHistory,
  faImage,
  faItalic,
  faInfoCircle,
  faLink,
  faList,
  faListOl,
  faLock,
  faLockOpen,
  faMinusCircle,
  faPaintBrush,
  faPaperclip,
  faPencilAlt,
  faPeopleArrows,
  faPlusCircle,
  faQuestionCircle,
  faQuoteLeft,
  faSearch,
  faShareAlt,
  faSignOutAlt,
  faSort,
  faSortDown,
  faSortUp,
  faSquare,
  faStar,
  faSyncAlt,
  faTags,
  faThumbtack,
  faTimes,
  faTools,
  faTrashAlt,
  faUpload,
  faUser,
  faUserCircle,
  faUsers,
} from '@fortawesome/free-solid-svg-icons';

library.add(
  faArrowUp,
  faArrowUpRightFromSquare,
  faArrowsDownToLine,
  faBold,
  faBoxArchive,
  faBug,
  faCalendarPlus,
  faChartPie,
  faCheck,
  faCheckSquare,
  faChevronCircleDown,
  faChevronCircleLeft,
  faChevronCircleRight,
  faChevronDown,
  faChevronLeft,
  faChevronRight,
  faClipboardCheck,
  faClipboardList,
  faClone,
  faCloud,
  faCode,
  faCogs,
  faComments,
  faCubes,
  faDna,
  faDownload,
  faEllipsisV,
  faEnvelope,
  faExclamationTriangle,
  faExternalLinkSquareAlt,
  faEye,
  faEyeSlash,
  faFile,
  faFileArchive,
  faFileCode,
  faFileExcel,
  faFileImage,
  faFileImport,
  faFilePdf,
  faFilePowerpoint,
  faFileVideo,
  faFileWord,
  faFingerprint,
  faHdd,
  faHeading,
  faHistory,
  faImage,
  faItalic,
  faInfoCircle,
  faLink,
  faList,
  faListOl,
  faLock,
  faLockOpen,
  faMinusCircle,
  faPaintBrush,
  faPaperclip,
  faPencilAlt,
  faPeopleArrows,
  faPlusCircle,
  faQuestionCircle,
  faQuoteLeft,
  faSearch,
  faShareAlt,
  faSignOutAlt,
  faSort,
  faSortDown,
  faSortUp,
  faSquare,
  faStar,
  faSyncAlt,
  faTags,
  faThumbtack,
  faTimes,
  faTools,
  faTrashAlt,
  faUpload,
  faUser,
  faUserCircle,
  faUsers,
);

// REGULAR
import { faBell, faCalendarAlt, faCalendarCheck, faCopy} from '@fortawesome/free-regular-svg-icons';
library.add(faBell, faCalendarAlt, faCalendarCheck, faCopy);

// BRANDS
import { faGithub, faGitter, faTwitter } from '@fortawesome/free-brands-svg-icons';
library.add(faGithub, faGitter, faTwitter);

// Kicks off the process of finding <i> tags and replacing with <svg>
dom.watch();
