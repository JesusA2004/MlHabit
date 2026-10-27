/**
 * Sistema de diseño de ML Habitat: primitivas de negocio compuestas sobre
 * shadcn-vue. Ver docs/design/DESIGN.md.
 */
export { default as AccountMenu } from './AccountMenu.vue';
export { default as AppToaster } from './AppToaster.vue';
export { default as ConfirmDialog } from './ConfirmDialog.vue';
export { default as DataPagination } from './DataPagination.vue';
export { default as DataView } from './DataView.vue';
export { default as DatePicker } from './DatePicker.vue';
export { default as DateSlotPicker } from './DateSlotPicker.vue';
export { default as DateText } from './DateText.vue';
export { default as EmptyState } from './EmptyState.vue';
export { default as FileDropzone } from './FileDropzone.vue';
export { default as FilterBar } from './FilterBar.vue';
export { default as FinishSwatches } from './FinishSwatches.vue';
export { default as FormSheet } from './FormSheet.vue';
export { default as FormStatus } from './FormStatus.vue';
export { default as KeyValue } from './KeyValue.vue';
export { default as Money } from './Money.vue';
export { default as PageContainer } from './PageContainer.vue';
export { default as PageHeader } from './PageHeader.vue';
export { default as ProgressSteps } from './ProgressSteps.vue';
export { default as SearchSelect } from './SearchSelect.vue';
export { default as SectionCard } from './SectionCard.vue';
export { default as StatusBadge } from './StatusBadge.vue';
export { default as TabBar } from './TabBar.vue';
export { default as Timeline } from './Timeline.vue';
export { default as UserAvatar } from './UserAvatar.vue';
export { default as SiteFooter } from './marketing/SiteFooter.vue';
export { default as SiteHeader } from './marketing/SiteHeader.vue';
export { default as SocialIcon } from './marketing/SocialIcon.vue';

export type { KeyValueItem } from './KeyValue.vue';
export type { ProgressStep } from './ProgressSteps.vue';
export type { SearchSelectOption } from './SearchSelect.vue';
export type { TimeSlot } from './DateSlotPicker.vue';
export type { TimelineEvent } from './Timeline.vue';
export type { DataColumn, RowKey } from './types';
export type { Tone } from './tones';
export { toneClasses } from './tones';
export { matchesAccept, useUploadQueue } from './useUploadQueue';
export type { UploadItem, UploadStatus } from './useUploadQueue';
