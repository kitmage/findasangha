#!/usr/bin/env python3
"""Generate the FaS Fluent Forms definitions into repo/data/forms/.

Field structures mirror Fluent Forms 6.2 DefaultElements.php and the demo
forms created on activation, so the JSON imports cleanly. Run, then create or
refresh the live forms with deploy/create-fluent-forms.php (wp eval-file).
"""
import json, os, itertools

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(REPO, "data", "forms")
os.makedirs(OUT, exist_ok=True)
_counter = itertools.count(1)

def key():
    return "el_fas_%03d" % next(_counter)

def req(value):
    return {"value": bool(value), "message": "This field is required",
            "global_message": "This field is required", "global": True}

def text(name, label, required=False, placeholder="", help_msg="", ftype="text"):
    return {
        "index": 0, "element": "input_text",
        "attributes": {"type": ftype, "name": name, "value": "", "id": "", "class": "",
                       "placeholder": placeholder},
        "settings": {"container_class": "", "label": label, "label_placement": "",
                     "admin_field_label": label, "help_message": help_msg,
                     "validation_rules": {"required": req(required)},
                     "conditional_logics": []},
        "editor_options": {"title": "Simple Text", "icon_class": "ff-edit-text",
                           "template": "inputText"},
        "uniqElKey": key(),
    }

def email(name, label, required=True, help_msg=""):
    return {
        "index": 0, "element": "input_email",
        "attributes": {"type": "email", "name": name, "value": "", "id": "", "class": "",
                       "placeholder": ""},
        "settings": {"container_class": "", "label": label, "label_placement": "",
                     "help_message": help_msg, "admin_field_label": label,
                     "validation_rules": {
                         "required": req(required),
                         "email": {"value": True, "message": "This field must contain a valid email",
                                   "global_message": "This field must contain a valid email", "global": True}},
                     "conditional_logics": []},
        "editor_options": {"title": "Email Address", "icon_class": "ff-edit-email",
                           "template": "inputText"},
        "uniqElKey": key(),
    }

def url(name, label, required=False, help_msg=""):
    return {
        "index": 0, "element": "input_url",
        "attributes": {"autocomplete": "", "type": "url", "name": name, "value": "",
                       "class": "", "placeholder": "https://"},
        "settings": {"container_class": "", "label": label, "admin_field_label": label,
                     "label_placement": "", "help_message": help_msg,
                     "prefix_label": "", "suffix_label": "",
                     "validation_rules": {
                         "required": req(required),
                         "url": {"value": True, "message": "This field must contain a valid url",
                                 "global_message": "This field must contain a valid url", "global": True}},
                     "conditional_logics": []},
        "editor_options": {"title": "Website URL", "icon_class": "ff-edit-website-url",
                           "template": "inputText"},
        "uniqElKey": key(),
    }

def textarea(name, label, required=False, rows=5, help_msg=""):
    return {
        "index": 0, "element": "textarea",
        "attributes": {"name": name, "value": "", "id": "", "class": "",
                       "placeholder": "", "rows": rows, "cols": 2},
        "settings": {"container_class": "", "label": label, "admin_field_label": label,
                     "label_placement": "", "help_message": help_msg,
                     "validation_rules": {"required": req(required)},
                     "conditional_logics": {"type": "any", "status": False,
                                             "conditions": [{"field": "", "value": "", "operator": ""}]}},
        "editor_options": {"title": "Text Area", "icon_class": "ff-edit-textarea",
                           "template": "inputTextarea"},
        "uniqElKey": key(),
    }

def adv_options(options, with_image=False):
    out = []
    for o in options:
        item = {"label": o, "value": o, "calc_value": ""}
        if with_image:
            item["image"] = ""
        out.append(item)
    return out

def select_el(name, label, options, required=False, help_msg=""):
    return {
        "index": 0, "element": "select",
        "attributes": {"autocomplete": "", "name": name, "value": "", "id": "", "class": ""},
        "settings": {"dynamic_default_value": "", "label": label, "admin_field_label": label,
                     "help_message": help_msg, "container_class": "", "label_placement": "",
                     "placeholder": "- Select -",
                     "advanced_options": adv_options(options),
                     "calc_value_status": False, "enable_image_input": False,
                     "values_visible": False, "enable_option_groups": "no",
                     "enable_select_2": "no",
                     "validation_rules": {"required": req(required)},
                     "conditional_logics": [], "randomize_options": "no"},
        "editor_options": {"title": "Dropdown", "icon_class": "ff-edit-dropdown",
                           "element": "select", "template": "select"},
        "uniqElKey": key(),
    }

def checkbox(name, label, options, required=False, help_msg=""):
    return {
        "index": 0, "element": "input_checkbox",
        "attributes": {"type": "checkbox", "name": name, "value": []},
        "settings": {"dynamic_default_value": "", "container_class": "", "label": label,
                     "admin_field_label": label, "label_placement": "", "display_type": "",
                     "help_message": help_msg,
                     "advanced_options": adv_options(options, with_image=True),
                     "calc_value_status": False, "enable_image_input": False,
                     "values_visible": False, "randomize_options": "no",
                     "enable_other_option": "no", "other_option_label": "Other",
                     "other_option_placeholder": "Please specify...",
                     "other_option_required_message": "Please specify a value for the selected \"Other\" option",
                     "validation_rules": {
                         "required": req(required),
                         "max_selection": {"value": "", "message": "Maximum selection exceeds",
                                           "global_message": "Maximum selection exceeds", "global": True},
                         "min_selection": {"value": "", "message": "Minimum selection is required",
                                           "global_message": "Minimum selection is required", "global": True}},
                     "conditional_logics": [], "layout_class": ""},
        "editor_options": {"title": "Checkbox", "icon_class": "ff-edit-checkbox-1",
                           "template": "inputCheckable"},
        "uniqElKey": key(),
    }

def html_el(html):
    return {
        "index": 0, "element": "custom_html", "attributes": [],
        "settings": {"html_codes": html, "conditional_logics": [], "container_class": ""},
        "editor_options": {"title": "Custom HTML", "icon_class": "ff-edit-html",
                           "template": "customHTML"},
        "uniqElKey": key(),
    }

def optin_checkbox():
    f = checkbox("newsletter_optin", "", ["yes"])
    f["settings"]["label"] = ""
    f["settings"]["admin_field_label"] = "Newsletter opt-in"
    f["settings"]["advanced_options"] = [{
        "label": "Email me occasional Find a Sangha updates. (Optional. This is the only marketing email we send, and you can unsubscribe any time.)",
        "value": "yes", "calc_value": "", "image": ""}]
    return f

def form_obj(title, fields, button_text):
    for i, f in enumerate(fields):
        f["index"] = i
    return {
        "fields": fields,
        "submitButton": {
            "uniqElKey": "el_1524065200616", "element": "button",
            "attributes": {"type": "submit", "class": ""},
            "settings": {"align": "left", "button_style": "default", "container_class": "",
                         "help_message": "", "background_color": "#1a7efb",
                         "button_size": "md", "color": "#ffffff",
                         "button_ui": {"type": "default", "text": button_text, "img_url": ""}},
            "editor_options": {"title": "Submit Button"},
        },
    }

def settings(message, require_login=False, login_msg=""):
    return {
        "confirmation": {"redirectTo": "samePage", "messageToShow": message,
                         "customPage": None, "samePageFormBehavior": "hide_form",
                         "customUrl": None},
        "restrictions": {
            "limitNumberOfEntries": {"enabled": False, "numberOfEntries": None,
                                     "period": "total",
                                     "limitReachedMsg": "Maximum number of entries exceeded."},
            "scheduleForm": {"enabled": False, "start": None, "end": None,
                             "selectedDays": ["Monday", "Tuesday", "Wednesday", "Thursday",
                                              "Friday", "Saturday", "Sunday"],
                             "pendingMsg": "Form submission is not started yet.",
                             "expiredMsg": "Form submission is now closed."},
            "requireLogin": {"enabled": bool(require_login), "requireLoginMsg": login_msg},
            "denyEmptySubmission": {"enabled": False,
                                    "message": "Sorry, you cannot submit an empty form."},
        },
        "layout": {"labelPlacement": "top", "helpMessagePlacement": "with_label",
                   "errorMessagePlacement": "inline", "asteriskPlacement": "asterisk-right"},
        "delete_entry_on_submission": "no",
        "appendSurveyResult": {"enabled": False, "showLabel": False, "showCount": False},
    }

# ---------------- Register ----------------
register_fields = [
    html_el("<p>A free account lets you submit a sangha and claim listings. We never sell your information, and your email is never displayed publicly.</p>"),
    text("first_name", "First name", required=True),
    text("last_name", "Last name", required=True),
    email("email", "Email address", required=True),
    text("username", "Username (optional)", help_msg="Leave blank and we will make one from your email address."),
    text("password", "Password", required=True, ftype="password",
         help_msg="At least 8 characters."),
    optin_checkbox(),
]
register = {
    "key": "register",
    "title": "FaS Create Account",
    "form_fields": form_obj("FaS Create Account", register_fields, "Create account"),
    "formSettings": settings("Welcome. Your account is ready and you are signed in. Next step: submit your sangha from the Add Your Sangha page."),
}

# ---------------- Submit a sangha ----------------
TRADITIONS = ["Insight", "Mahayana", "Nichiren", "Non-sectarian", "Other", "Plum Village",
              "Pure Land", "Theravada", "Triratna", "Vajrayana", "Zen"]
FORMATS = ["Online", "In person", "Hybrid"]
LANGUAGES = ["English", "Spanish", "Vietnamese"]
FEATURES = ["Beginner friendly", "Family friendly", "LGBTQ+ welcoming", "Peer led",
            "Prison or hospital outreach", "Retreats", "Teacher led",
            "Wheelchair accessible", "Young adult group"]
DANA = ["Free, by donation", "Suggested donation", "Set fee", "Membership based", "Not applicable"]

submit_fields = [
    html_el("<p>Every listing is checked by hand before it appears in the directory. Verification means we confirmed the sangha exists and its details are current; it never means endorsement of a teacher.</p><p>Home-based sanghas: you may leave the street address blank and list at city level only.</p>"),
    text("sangha_name", "Sangha name", required=True),
    textarea("description", "Description", required=True, rows=5,
             help_msg="A sentence or two about the sangha, in plain language."),
    url("website_url", "Website"),
    email("contact_email", "Contact email", required=True,
          help_msg="Used only for verification and updates. Never displayed publicly."),
    checkbox("formats", "Format", FORMATS, required=True),
    checkbox("traditions", "Tradition", TRADITIONS, required=True),
    text("lineage", "Lineage or school (optional)"),
    text("teacher", "Teacher or facilitator (optional)"),
    text("affiliation", "Affiliation (optional)", help_msg="Order, network, or parent organization, if any."),
    text("meeting_day", "Meeting day(s)", help_msg="e.g. Sundays; Tuesdays and Thursdays"),
    text("meeting_time", "Meeting time", help_msg="e.g. 9:00-10:30 am"),
    text("sangha_timezone", "Sangha timezone", required=True,
         help_msg="e.g. America/Chicago, America/New_York, Europe/London"),
    text("meeting_address", "Street address (optional)"),
    text("city", "City", required=True),
    text("region", "State / region (optional)"),
    text("country", "Country", required=True),
    text("latitude", "Latitude (optional)", help_msg="If you know it. We can look it up during verification."),
    text("longitude", "Longitude (optional)"),
    checkbox("languages", "Languages", LANGUAGES),
    text("language_other", "Other language (optional)"),
    checkbox("features", "Features", FEATURES),
    select_el("dana_model", "Dana / cost model", DANA),
    url("source_url", "Verification source (optional)",
        help_msg="A page where we can confirm these details, if the website above does not cover them."),
    optin_checkbox(),
]
submit = {
    "key": "submit",
    "title": "FaS Submit a Sangha",
    "form_fields": form_obj("FaS Submit a Sangha", submit_fields, "Submit for review"),
    "formSettings": settings(
        "Thank you. Your listing was received and is waiting for a hand check. It will appear in the directory once it is verified.",
        require_login=True,
        login_msg="Please sign in before submitting. No account yet? Create one free on the Create Account page, then come back here."),
}

# ---------------- Claim a listing ----------------
ROLES = ["Teacher or facilitator", "Organizer or volunteer", "Member of the sangha", "Other"]
claim_fields = [
    html_el("<p>Claiming a listing lets you keep your sangha's details current. Claims are reviewed by hand. We usually verify by email at an address on the sangha's own website, or by a link placed on that site.</p>"),
    text("listing_ref", "Listing name or findasangha.com URL", required=True),
    text("claimant_name", "Your name", required=True),
    email("claimant_email", "Your email", required=True,
          help_msg="An address at the sangha's own domain speeds verification up."),
    select_el("claimant_role", "Your connection to this sangha", ROLES, required=True),
    url("evidence_url", "Evidence link (optional)",
        help_msg="A page that shows your connection to the sangha, if you have one."),
    textarea("message", "Anything we should know", required=True, rows=4,
             help_msg="Corrections to the listing are welcome here too."),
    optin_checkbox(),
]
claim = {
    "key": "claim",
    "title": "FaS Claim a Listing",
    "form_fields": form_obj("FaS Claim a Listing", claim_fields, "Send claim"),
    "formSettings": settings(
        "Thank you. Your claim was received. Claims are reviewed by hand, and we will email you with the outcome.",
        require_login=True,
        login_msg="Please sign in before claiming. No account yet? Create one free on the Create Account page, then come back here."),
}

for doc in (register, submit, claim):
    path = os.path.join(OUT, doc["key"] + "-form.json")
    with open(path, "w") as fh:
        json.dump(doc, fh, indent=2)
    print("wrote", path)
