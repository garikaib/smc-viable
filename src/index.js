import { registerBlockType } from '@wordpress/blocks';
import './index.scss';
import Edit from './blocks/quiz/edit';
import save from './blocks/quiz/save';
import metadata from './blocks/quiz/block.json';

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: save,
});
