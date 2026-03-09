/**
 * Block editor sidebar for Learn plugin.
 *
 * Provides feedback panels for lessons and assessments,
 * activity meta box, and content locking notice.
 *
 * Uses @wordpress/* packages bundled with WordPress — no npm.
 *
 * @package Learn
 */

/* global learnEditor, wp */

( function () {
	'use strict';

	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var registerPlugin  = wp.plugins.registerPlugin;
	var PluginSidebar   = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelBody       = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var Button          = wp.components.Button;
	var Notice          = wp.components.Notice;
	var dispatch        = wp.data.dispatch;

	if ( ! learnEditor || ! learnEditor.isGenerated ) {
		return;
	}

	// Show locked content notice.
	if ( ! learnEditor.isPublished ) {
		dispatch( 'core/notices' ).createNotice(
			'info',
			learnEditor.i18n.lockedNotice,
			{
				id: 'learn-locked-notice',
				isDismissible: false,
			}
		);
	} else {
		dispatch( 'core/notices' ).createNotice(
			'warning',
			learnEditor.i18n.publishedNotice,
			{
				id: 'learn-published-notice',
				isDismissible: false,
			}
		);
	}

	// -------------------------------------------------------------------------
	// Feedback Sidebar Panel.
	// -------------------------------------------------------------------------

	function LearnSidebar() {
		var isFinal     = learnEditor.isFinalAssessment;
		var isPublished = learnEditor.isPublished;

		var panelTitle = isFinal
			? learnEditor.i18n.assessmentPanel
			: learnEditor.i18n.feedbackPanel;

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, {
				target: 'learn-feedback-sidebar',
				icon: 'welcome-learn-more',
			}, learnEditor.i18n.pluginTitle ),
			el( PluginSidebar, {
				name: 'learn-feedback-sidebar',
				title: panelTitle,
				icon: 'welcome-learn-more',
			},
				el( PanelBody, { title: panelTitle, initialOpen: true },
					isFinal
						? el( AssessmentFeedbackPanel, null )
						: el( LessonFeedbackPanel, null )
				),
				learnEditor.activity && ! isFinal
					? el( PanelBody, { title: 'Activity', initialOpen: false },
						el( ActivityPanel, null )
					)
					: null,
				el( PanelBody, { title: 'Details', initialOpen: false },
					el( DetailsPanel, null )
				)
			)
		);
	}

	// -------------------------------------------------------------------------
	// Lesson Feedback Panel.
	// -------------------------------------------------------------------------

	function LessonFeedbackPanel() {
		var state = useState( '' );
		var feedback = state[0];
		var setFeedback = state[1];

		var statusState = useState( null );
		var status = statusState[0];
		var setStatus = statusState[1];

		var loadingState = useState( false );
		var loading = loadingState[0];
		var setLoading = loadingState[1];

		if ( learnEditor.isPublished ) {
			return el( 'p', null, learnEditor.i18n.publishedNotice );
		}

		function handleRegenerate() {
			if ( ! feedback.trim() ) {
				return;
			}
			setLoading( true );
			setStatus( null );

			submitFeedback( 'lesson', {
				post_id: learnEditor.postId,
				feedback: feedback,
			} ).then( function ( data ) {
				setLoading( false );
				if ( data.success ) {
					setStatus( 'success' );
					setFeedback( '' );
				} else {
					setStatus( 'error' );
				}
			} ).catch( function () {
				setLoading( false );
				setStatus( 'error' );
			} );
		}

		return el( Fragment, null,
			el( 'p', { className: 'learn-sidebar-version' },
				learnEditor.i18n.version + ': ' + learnEditor.lessonVersion
			),
			status === 'success'
				? el( Notice, { status: 'success', isDismissible: false }, learnEditor.i18n.regenerateSuccess )
				: null,
			status === 'error'
				? el( Notice, { status: 'error', isDismissible: false }, learnEditor.i18n.regenerateError )
				: null,
			el( TextareaControl, {
				label: learnEditor.i18n.feedbackLabel,
				value: feedback,
				onChange: setFeedback,
				rows: 4,
			} ),
			el( Button, {
				variant: 'primary',
				onClick: handleRegenerate,
				disabled: loading || ! feedback.trim(),
				isBusy: loading,
			}, loading ? learnEditor.i18n.regenerating : learnEditor.i18n.regenerate )
		);
	}

	// -------------------------------------------------------------------------
	// Assessment Feedback Panel.
	// -------------------------------------------------------------------------

	function AssessmentFeedbackPanel() {
		var state = useState( '' );
		var feedback = state[0];
		var setFeedback = state[1];

		var statusState = useState( null );
		var status = statusState[0];
		var setStatus = statusState[1];

		var loadingState = useState( false );
		var loading = loadingState[0];
		var setLoading = loadingState[1];

		if ( learnEditor.isPublished ) {
			return el( 'p', null, learnEditor.i18n.publishedNotice );
		}

		var assessment = learnEditor.activity;

		function handleRegenerate() {
			if ( ! feedback.trim() ) {
				return;
			}
			setLoading( true );
			setStatus( null );

			submitFeedback( 'assessment', {
				post_id: learnEditor.postId,
				course_term_id: learnEditor.courseTermId,
				feedback: feedback,
			} ).then( function ( data ) {
				setLoading( false );
				if ( data.success ) {
					setStatus( 'success' );
					setFeedback( '' );
				} else {
					setStatus( 'error' );
				}
			} ).catch( function () {
				setLoading( false );
				setStatus( 'error' );
			} );
		}

		return el( Fragment, null,
			assessment
				? el( Fragment, null,
					assessment.prompt
						? el( 'div', { className: 'learn-review-section' },
							el( 'strong', null, 'Prompt: ' ),
							el( 'p', null, assessment.prompt )
						)
						: null,
					assessment.portfolio_rubric
						? el( 'div', { className: 'learn-review-section' },
							el( 'strong', null, 'Portfolio Rubric' ),
							assessment.portfolio_rubric.map( function ( r, i ) {
								return el( 'div', { key: i },
									el( 'p', null, el( 'em', null, r.objective ) ),
									el( 'ul', null, r.criteria.map( function ( c, j ) {
										return el( 'li', { key: j }, c );
									} ) )
								);
							} )
						)
						: null
				)
				: null,
			el( 'p', { className: 'learn-sidebar-version' },
				learnEditor.i18n.version + ': ' + learnEditor.activityVersion
			),
			status === 'success'
				? el( Notice, { status: 'success', isDismissible: false }, learnEditor.i18n.regenerateSuccess )
				: null,
			status === 'error'
				? el( Notice, { status: 'error', isDismissible: false }, learnEditor.i18n.regenerateError )
				: null,
			el( TextareaControl, {
				label: learnEditor.i18n.assessmentFeedbackLabel,
				value: feedback,
				onChange: setFeedback,
				rows: 4,
			} ),
			el( Button, {
				variant: 'primary',
				onClick: handleRegenerate,
				disabled: loading || ! feedback.trim(),
				isBusy: loading,
			}, loading ? learnEditor.i18n.regenerating : learnEditor.i18n.regenerateAssessment )
		);
	}

	// -------------------------------------------------------------------------
	// Activity Panel (in sidebar).
	// -------------------------------------------------------------------------

	function ActivityPanel() {
		var activity = learnEditor.activity;
		var review   = learnEditor.review;

		var state = useState( '' );
		var feedback = state[0];
		var setFeedback = state[1];

		var statusState = useState( null );
		var status = statusState[0];
		var setStatus = statusState[1];

		var loadingState = useState( false );
		var loading = loadingState[0];
		var setLoading = loadingState[1];

		if ( ! activity ) {
			return el( 'p', null, 'No activity data.' );
		}

		function handleRegenerate() {
			if ( ! feedback.trim() ) {
				return;
			}
			setLoading( true );
			setStatus( null );

			submitFeedback( 'activity', {
				post_id: learnEditor.postId,
				feedback: feedback,
			} ).then( function ( data ) {
				setLoading( false );
				if ( data.success ) {
					setStatus( 'success' );
					setFeedback( '' );
				} else {
					setStatus( 'error' );
				}
			} ).catch( function () {
				setLoading( false );
				setStatus( 'error' );
			} );
		}

		return el( Fragment, null,
			el( 'div', { className: 'learn-activity-section' },
				el( 'span', { className: 'learn-badge learn-badge--' + learnEditor.activityType },
					learnEditor.activityType ? learnEditor.activityType.toUpperCase() : ''
				),
				' ',
				el( 'span', { className: 'learn-xp' }, learnEditor.xpValue + ' XP' ),
				learnEditor.milestone
					? el( 'span', { className: 'learn-xp' }, ' | ' + learnEditor.milestone )
					: null
			),
			activity.prompt
				? el( 'p', null, el( 'strong', null, 'Prompt: ' ), activity.prompt )
				: null,
			activity.instructions
				? el( 'p', null, el( 'strong', null, 'Instructions: ' ), activity.instructions )
				: null,
			activity.scoring_rubric
				? el( Fragment, null,
					el( 'strong', null, 'Rubric:' ),
					el( 'ul', null, activity.scoring_rubric.map( function ( r, i ) {
						return el( 'li', { key: i }, r );
					} ) )
				)
				: null,
			activity.hints
				? el( Fragment, null,
					el( 'strong', null, 'Hints:' ),
					el( 'ul', null, activity.hints.map( function ( h, i ) {
						return el( 'li', { key: i }, h );
					} ) )
				)
				: null,
			learnEditor.portfolio
				? el( 'p', null, el( 'strong', null, 'Portfolio: ' ), learnEditor.portfolio )
				: null,
			review
				? el( 'p', null,
					el( 'strong', null, 'Review: ' ),
					el( 'span', { className: 'learn-verdict learn-verdict--' + review.verdict },
						review.verdict === 'approved' ? 'Approved' : 'Revised'
					)
				)
				: null,
			el( 'p', { className: 'learn-sidebar-version' },
				learnEditor.i18n.version + ': ' + learnEditor.activityVersion
			),
			! learnEditor.isPublished
				? el( Fragment, null,
					status === 'success'
						? el( Notice, { status: 'success', isDismissible: false }, learnEditor.i18n.regenerateSuccess )
						: null,
					status === 'error'
						? el( Notice, { status: 'error', isDismissible: false }, learnEditor.i18n.regenerateError )
						: null,
					el( TextareaControl, {
						label: 'What should change about this activity?',
						value: feedback,
						onChange: setFeedback,
						rows: 3,
					} ),
					el( Button, {
						variant: 'secondary',
						onClick: handleRegenerate,
						disabled: loading || ! feedback.trim(),
						isBusy: loading,
					}, loading ? learnEditor.i18n.regenerating : 'Regenerate Activity' )
				)
				: null
		);
	}

	// -------------------------------------------------------------------------
	// Details Panel.
	// -------------------------------------------------------------------------

	function DetailsPanel() {
		var takeaways = learnEditor.takeaways;

		return el( Fragment, null,
			takeaways && takeaways.length
				? el( Fragment, null,
					el( 'strong', null, 'Key Takeaways' ),
					el( 'ul', null, takeaways.map( function ( t, i ) {
						return el( 'li', { key: i }, t );
					} ) )
				)
				: null,
			el( 'p', null,
				el( 'strong', null, 'Post ID: ' ), learnEditor.postId
			),
			learnEditor.courseTermId
				? el( 'p', null,
					el( 'strong', null, 'Course Term: ' ), learnEditor.courseTermId
				)
				: null
		);
	}

	// -------------------------------------------------------------------------
	// AJAX Helper.
	// -------------------------------------------------------------------------

	function submitFeedback( level, params ) {
		var formData = new FormData();
		formData.append( 'action', '1111_submit_feedback' );
		formData.append( 'nonce', learnEditor.nonce );
		formData.append( 'level', level );

		Object.keys( params ).forEach( function ( key ) {
			formData.append( key, params[ key ] );
		} );

		return fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	// -------------------------------------------------------------------------
	// Register Plugin.
	// -------------------------------------------------------------------------

	registerPlugin( 'learn-feedback', {
		render: LearnSidebar,
		icon: 'welcome-learn-more',
	} );
} )();
