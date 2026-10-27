import type { Component } from 'vue';
import BlockButtons from './elements/BlockButtons.vue';
import BlockDivider from './elements/BlockDivider.vue';
import BlockEyebrow from './elements/BlockEyebrow.vue';
import BlockHeading from './elements/BlockHeading.vue';
import BlockImage from './elements/BlockImage.vue';
import BlockPageBody from './elements/BlockPageBody.vue';
import BlockPostMeta from './elements/BlockPostMeta.vue';
import BlockQuestion from './elements/BlockQuestion.vue';
import BlockQuote from './elements/BlockQuote.vue';
import BlockShare from './elements/BlockShare.vue';
import BlockSocialLinks from './elements/BlockSocialLinks.vue';
import BlockSpacer from './elements/BlockSpacer.vue';
import BlockText from './elements/BlockText.vue';
import BlockVideo from './elements/BlockVideo.vue';

/**
 * Registro de bloques permitidos. Un tipo que no esté aquí (o en
 * schema.json) nunca se renderiza en el sitio público.
 */
export const elementComponents: Record<string, Component> = {
    eyebrow: BlockEyebrow,
    heading: BlockHeading,
    text: BlockText,
    buttons: BlockButtons,
    image: BlockImage,
    video: BlockVideo,
    quote: BlockQuote,
    question: BlockQuestion,
    divider: BlockDivider,
    spacer: BlockSpacer,
    'social-links': BlockSocialLinks,
    'page-body': BlockPageBody,
    'post-meta': BlockPostMeta,
    share: BlockShare,
};
