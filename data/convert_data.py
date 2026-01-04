import pandas as pd
import json
import re

file_path = 'SMC Businesses Assessment Tool (1).xlsx'
input_sheet = 'Basic Assessment'

def parse_options(cell_value):
    if not isinstance(cell_value, str):
        return {"type": "unknown", "raw": str(cell_value)}
    
    if "Lorem ipsum" in cell_value:
        return {"type": "open_ended"}
    
    # Attempt to parse options like "GREAT 15"
    options = []
    lines = cell_value.strip().split('\n')
    for line in lines:
        line = line.strip()
        if not line:
            continue
        # Look for the last number in the string which is the score
        match = re.search(r'(.*?)\s+(-?\d+)$', line)
        if match:
            label = match.group(1).strip()
            score = int(match.group(2))
            options.append({"label": label, "score": score})
        else:
            # Fallback for lines that might not match perfectly
            options.append({"label": line, "score": None})
            
    return {"type": "dropdown", "options": options}

try:
    xl = pd.ExcelFile(file_path)
    df = xl.parse(input_sheet, header=None)
    
    questions = []
    # Rows 7 to 36 (indices 6 to 35)
    for index in range(6, 36):
        # Check if row is within bounds
        if index >= len(df):
            break
            
        row = df.iloc[index]
        stage = row[0]
        question = row[1]
        options_raw = row[2]
        
        # Skip empty rows if any
        if pd.isna(question):
            continue

        q_data = {
            "stage": stage if not pd.isna(stage) else None,
            "question": question,
            "response_config": parse_options(options_raw)
        }
        questions.append(q_data)

    dashboard = []
    # Rows 43 to 46 (indices 42 to 45)
    for index in range(42, 46):
         if index >= len(df):
            break
         row = df.iloc[index]
         # Capturing assumed structure for dashboard - simply extracting non-null values for now
         row_data = row.dropna().tolist()
         dashboard.append(row_data)

    final_data = {
        "assessment_questions": questions,
        "dashboard_summary": dashboard
    }
    
    # Print a preview of the JSON to verify before saving
    print(json.dumps(final_data, indent=2))
    
    # Saving to file
    with open('assessment_extracted.json', 'w') as f:
        json.dump(final_data, f, indent=2)

except Exception as e:
    print(f"Error: {e}")
